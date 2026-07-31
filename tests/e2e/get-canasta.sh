#!/usr/bin/env bash
# get-canasta.sh — Install or upgrade to the Ansible-based Canasta CLI.
#
# This is a vendored copy of the installer published at
# https://get.canasta.wiki, kept in the repository so that the end-to-end
# workflow does not depend on that host being reachable. Refresh it from
# upstream when the installer changes.
#
# By default this installs the latest released version of the CLI (the
# highest vX.Y.Z tag); pass --dev to track the development branch (the
# head of main) instead. This mirrors 'canasta upgrade', which also
# defaults to the latest release and takes --dev for development builds.
#
# Usage:
#   ./get-canasta.sh
#   ./get-canasta.sh --native
#   ./get-canasta.sh --docker
#   ./get-canasta.sh --dev
#
# Documentation: https://canasta.wiki/wiki/Help:Installation
#
# Flags:
#   --native    Install canasta-native (requires Python 3.10+, git)
#   --docker    Install canasta-docker (requires Docker only, default)
#   --dev       Track the development branch (head of main) instead of
#               the latest released version
#   --prefix    Installation prefix (default: /opt/canasta-ansible for native)
#
# Linux native installs create a 'canasta' system group. Add users with:
#   sudo usermod -aG canasta $USER
#
# macOS native installs use a user-owned path (no group needed).
#
# Upgrading from Canasta-Go (legacy Go CLI) — the installer detects an
# existing Go binary at /usr/local/bin/canasta and replaces it with a
# symlink to the new wrapper. The registered-instance registry
# (conf.json) and instance directories are unchanged; the new Canasta
# CLI reads the same format.

set -euo pipefail

REPO_URL="https://github.com/CanastaWiki/Canasta-CLI.git"
BIN_DIR="/usr/local/bin"
MODE=""
PREFIX=""
DEV=false

# --- Helpers -----------------------------------------------------------------

info()  { printf '\033[1;34m%s\033[0m\n' "$*"; }
warn()  { printf '\033[1;33m%s\033[0m\n' "$*" >&2; }
error() { printf '\033[1;31m%s\033[0m\n' "$*" >&2; exit 1; }

need_cmd() {
    if ! command -v "$1" >/dev/null 2>&1; then
        error "Required command not found: $1"
    fi
}

# Highest vX.Y.Z release tag on the repo (e.g. v4.0.4); empty on failure.
# Prefers git ls-remote (no API rate limits); falls back to the GitHub
# tags API via curl. The Releases API is intentionally not used — it pins
# v3.7.0 as 'latest' for legacy clients. Mirrors the resolver in canasta.py
# and the canasta-docker wrapper.
latest_release_tag() {
    local tag=""
    if command -v git >/dev/null 2>&1; then
        tag="$(git ls-remote --tags --refs "$REPO_URL" 'v*' 2>/dev/null \
            | sed 's#.*refs/tags/##' \
            | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' \
            | sort -V | tail -1 || true)"
    fi
    if [[ -z "$tag" ]] && command -v curl >/dev/null 2>&1; then
        tag="$(curl -fsSL "https://api.github.com/repos/CanastaWiki/Canasta-CLI/tags" 2>/dev/null \
            | grep -oE '"name": *"v[0-9]+\.[0-9]+\.[0-9]+"' \
            | grep -oE 'v[0-9]+\.[0-9]+\.[0-9]+' \
            | sort -V | tail -1 || true)"
    fi
    printf '%s' "$tag"
}

# Point a freshly cloned/updated native checkout at the selected channel:
# the latest release tag by default (detached checkout), or the head of
# main with --dev. $1 = install dir; $2 = sudo prefix ("$SUDO" or "").
set_native_channel() {
    local install_dir="$1"
    local sudo_cmd="${2:-}"
    if [[ "$DEV" == true ]]; then
        info "Tracking development branch (head of main)..."
        $sudo_cmd git -C "$install_dir" checkout --quiet main
        $sudo_cmd git -C "$install_dir" pull --ff-only --quiet
        return
    fi
    local tag
    tag="$(latest_release_tag)"
    if [[ -z "$tag" ]]; then
        warn "Could not determine the latest release tag; staying on the default branch (development)."
        return
    fi
    info "Checking out latest release ${tag}..."
    $sudo_cmd git -C "$install_dir" fetch --tags --quiet origin || true
    $sudo_cmd git -C "$install_dir" checkout --quiet "$tag"
}

# The user who will actually run 'canasta' (not necessarily root, when the
# installer is invoked via sudo). Used to place the Docker-mode channel pin
# in that user's config dir.
target_user() { echo "${SUDO_USER:-$(id -un)}"; }

# Resolve the config dir the canasta-docker wrapper will use for the target
# user, mirroring the wrapper's own logic (minus the root /etc/canasta case,
# since the pin is for the non-root user who runs canasta).
canasta_config_dir() {
    local u h
    u="$(target_user)"
    h="$(getent passwd "$u" 2>/dev/null | cut -d: -f6)"
    [[ -z "$h" ]] && h="$(eval echo "~$u" 2>/dev/null)"
    [[ -z "$h" ]] && return 0
    if [[ "$(uname)" == "Darwin" ]]; then
        echo "$h/Library/Application Support/canasta"
    elif [[ "$u" == "$(id -un)" && -n "${XDG_CONFIG_HOME:-}" ]]; then
        echo "$XDG_CONFIG_HOME/canasta"
    else
        echo "$h/.config/canasta"
    fi
}

# Best-effort: pin the Docker-mode image channel so the first command runs
# the right image before any 'canasta upgrade'. A later upgrade rewrites
# this, so a miss here is harmless. $1 = tag to pin ('latest' or a version).
pin_docker_channel() {
    local tag="$1" cfg
    cfg="$(canasta_config_dir)" || return 0
    [[ -z "$cfg" ]] && return 0
    $SUDO mkdir -p "$cfg" 2>/dev/null || return 0
    printf '%s\n' "$tag" | $SUDO tee "$cfg/cli_image_tag" >/dev/null 2>&1 || return 0
    $SUDO chown -R "$(target_user)" "$cfg" 2>/dev/null || true
}

install_native_deps() {
    if command -v apt-get >/dev/null 2>&1; then
        info "Installing system dependencies (git, python3, python3-venv)..."
        $SUDO apt-get update -qq
        $SUDO apt-get install -y -qq git python3 python3-venv >/dev/null
    elif command -v dnf >/dev/null 2>&1; then
        info "Installing system dependencies (git, python3)..."
        $SUDO dnf install -y -q git python3
    elif command -v yum >/dev/null 2>&1; then
        info "Installing system dependencies (git, python3)..."
        $SUDO yum install -y -q git python3
    fi
}

check_python() {
    if ! command -v python3 >/dev/null 2>&1; then
        error "python3 not found. Install it and re-run."
    fi
    local pyver
    pyver="$(python3 -c 'import sys; print(sys.version_info.minor)' 2>/dev/null)" || \
        error "Could not determine Python version"
    if [[ "$pyver" -lt 10 ]]; then
        error "Python 3.10+ required (found 3.${pyver})"
    fi
    local test_venv
    test_venv="$(mktemp -d)"
    if ! python3 -m venv "$test_venv" 2>/dev/null; then
        rm -rf "$test_venv"
        if [[ "$(uname -s)" == "Darwin" ]]; then
            error "python3 -m venv failed. Try: brew install python3"
        else
            error "python3 -m venv failed. On Debian/Ubuntu: sudo apt install python3-venv"
        fi
    fi
    rm -rf "$test_venv"
}

check_docker() {
    need_cmd docker
    if ! docker info >/dev/null 2>&1; then
        warn "Docker is installed but the daemon is not running."
        warn "Start it with: sudo systemctl start docker"
        warn "Continuing with install — you'll need Docker running to use Canasta."
    fi
}

need_sudo() {
    if [[ "$(id -u)" != "0" ]]; then
        if command -v sudo >/dev/null 2>&1; then
            SUDO="sudo"
        else
            error "This operation requires root. Run with sudo or as root."
        fi
    else
        SUDO=""
    fi
}

# --- Existing-install detection ---------------------------------------------

# Classify whatever's at /usr/local/bin/canasta so the installer can
# announce an upgrade path when the legacy Canasta-Go binary is
# replaced. The classifier is deliberately narrow — the installer's
# behavior doesn't change based on the result. Only the user-facing
# message does.
#
# Results:
#   none             — nothing installed yet
#   ansible          — already using the Ansible-based CLI (symlink to
#                      canasta-native or canasta-docker)
#   go-cli           — a regular file at /usr/local/bin/canasta,
#                      presumed to be the legacy Go binary (Canasta-Go
#                      installs via 'make install' which copies a plain
#                      binary into place)
#   symlink-other    — a symlink pointing somewhere unexpected
detect_existing_install() {
    local canasta_path="${BIN_DIR}/canasta"
    EXISTING_INSTALL="none"

    if [[ ! -e "$canasta_path" && ! -L "$canasta_path" ]]; then
        return
    fi

    if [[ -L "$canasta_path" ]]; then
        local target
        target="$(readlink "$canasta_path")"
        case "$target" in
            *canasta-native|*canasta-docker) EXISTING_INSTALL="ansible" ;;
            *)                               EXISTING_INSTALL="symlink-other" ;;
        esac
    elif [[ -f "$canasta_path" ]]; then
        EXISTING_INSTALL="go-cli"
    fi
}

announce_upgrade() {
    if [[ "$EXISTING_INSTALL" != "go-cli" ]]; then
        return
    fi
    info ""
    info "========================================"
    info "Upgrading the legacy (Go-based) Canasta CLI"
    info "========================================"
    info "Detected an existing Canasta CLI 3.x binary at ${BIN_DIR}/canasta."
    info "This installer replaces it with the new Ansible-based Canasta CLI 4.x."
    info ""
    info "Your registered instances in conf.json continue to work without"
    info "modification — the Canasta CLI reads the same registry format."
    info ""
}

# --- Platform detection ------------------------------------------------------

detect_platform() {
    OS="$(uname -s)"
    ARCH="$(uname -m)"

    IS_WSL=false
    if [[ -f /proc/version ]] && grep -qi microsoft /proc/version 2>/dev/null; then
        IS_WSL=true
    fi

    case "$OS" in
        Linux)  PLATFORM="linux" ;;
        Darwin) PLATFORM="macos" ;;
        *)      error "Unsupported platform: $OS" ;;
    esac
}

# --- Parse arguments ---------------------------------------------------------

parse_args() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --native) MODE="native" ;;
            --docker) MODE="docker" ;;
            --dev) DEV=true ;;
            --prefix) PREFIX="$2"; shift ;;
            --prefix=*) PREFIX="${1#*=}" ;;
            -h|--help)
                echo "Usage: get-canasta.sh [--native|--docker] [--dev] [--prefix PATH]"
                exit 0
                ;;
            *) error "Unknown option: $1" ;;
        esac
        shift
    done

    if [[ -z "$MODE" ]]; then
        MODE="docker"
    fi
}

# --- Docker mode install -----------------------------------------------------

install_docker_mode() {
    info "Installing Canasta (Docker mode)..."

    check_docker
    need_cmd curl
    need_sudo

    # Select the channel: the latest released wrapper + image tag by
    # default, or the head of main with --dev.
    local wrapper_ref="main"
    local pin_tag="latest"
    if [[ "$DEV" != true ]]; then
        local tag
        tag="$(latest_release_tag)"
        if [[ -n "$tag" ]]; then
            wrapper_ref="$tag"
            pin_tag="${tag#v}"
        else
            warn "Could not determine the latest release; using main (development)."
        fi
    fi
    local wrapper_url="https://raw.githubusercontent.com/CanastaWiki/Canasta-CLI/${wrapper_ref}/canasta-docker"

    info "Downloading canasta-docker wrapper (${wrapper_ref})..."
    $SUDO curl -fsSL --retry 3 --retry-delay 5 \
        -o "${BIN_DIR}/canasta-docker" "$wrapper_url"
    $SUDO chmod +x "${BIN_DIR}/canasta-docker"

    # Pin the image channel so the first command runs the chosen image.
    pin_docker_channel "$pin_tag"

    info "Creating canasta symlink..."
    $SUDO ln -sf "${BIN_DIR}/canasta-docker" "${BIN_DIR}/canasta"

    info ""
    info "Canasta installed (Docker mode)."
    info "  canasta-docker: ${BIN_DIR}/canasta-docker"
    info "  canasta:        ${BIN_DIR}/canasta -> canasta-docker"
    info ""
    info "Verify: canasta version"
}

# --- Native mode install (Linux) --------------------------------------------

install_native_linux() {
    # The canasta-ansible name is pinned, not stale: it matches the
    # published image tag and the prefix on already-installed hosts.
    local install_dir="${PREFIX:-/opt/canasta-ansible}"

    need_sudo
    install_native_deps

    need_cmd git
    check_python

    info "Installing Canasta (native mode, Linux)..."

    if [[ "$IS_WSL" == true ]]; then
        warn "Detected WSL. Native install will work in WSL2."
        warn "If using WSL1, Docker integration may have issues."
    fi

    # Create canasta group if it doesn't exist
    if ! getent group canasta >/dev/null 2>&1; then
        info "Creating 'canasta' system group..."
        $SUDO groupadd --system canasta
    fi

    # Clone or update the repo, then move to the selected channel.
    if [[ -d "${install_dir}/.git" ]]; then
        info "Updating existing installation at ${install_dir}..."
    else
        info "Cloning Canasta CLI to ${install_dir}..."
        $SUDO git clone "$REPO_URL" "$install_dir"
    fi
    set_native_channel "$install_dir" "$SUDO"

    # Mark as safe directory for git (so non-root users can run git
    # commands against the root-owned repo during e.g. 'canasta upgrade')
    $SUDO git config --system --add safe.directory "$install_dir" 2>/dev/null || true

    # Create venv and install deps
    info "Creating Python virtual environment..."
    $SUDO python3 -m venv "${install_dir}/.venv"
    $SUDO "${install_dir}/.venv/bin/pip" install --quiet -r "${install_dir}/requirements.txt"

    # Build metadata (written as root, group-fixed in the final pass below)
    info "Writing build metadata..."
    $SUDO bash -c "cd '${install_dir}' && git rev-parse --short HEAD > BUILD_COMMIT && git log -1 --format=%cd --date=format:'%Y-%m-%d %H:%M:%S' > BUILD_DATE"

    # Install Ansible collections (system path, not root's home)
    if [[ -f "${install_dir}/requirements.yml" ]]; then
        info "Installing Ansible collections..."
        $SUDO "${install_dir}/.venv/bin/ansible-galaxy" collection install \
            -r "${install_dir}/requirements.yml" \
            -p /usr/share/ansible/collections 2>/dev/null || true
    fi

    # Set group ownership AFTER all file writes so BUILD_COMMIT,
    # BUILD_DATE, and venv files are group-writable. Running before
    # these writes leaves the new files root:root, which blocks
    # non-root upgrades.
    info "Setting group ownership to 'canasta'..."
    $SUDO chgrp -R canasta "$install_dir"
    $SUDO chmod -R g+w "$install_dir"

    # Create symlinks
    info "Creating symlinks in ${BIN_DIR}..."
    $SUDO ln -sf "${install_dir}/canasta-native" "${BIN_DIR}/canasta-native"
    $SUDO ln -sf "${install_dir}/canasta-docker" "${BIN_DIR}/canasta-docker"
    $SUDO ln -sf "${BIN_DIR}/canasta-native" "${BIN_DIR}/canasta"

    # Install Docker if missing
    if ! command -v docker >/dev/null 2>&1 || ! docker info >/dev/null 2>&1; then
        canasta install docker
    fi

    # Add current user to required groups
    local _user="${SUDO_USER:-$(whoami)}"
    info "Adding ${_user} to canasta, docker, and www-data groups..."
    $SUDO usermod -aG canasta,docker,www-data "$_user" 2>/dev/null || true
}

# --- Native mode install (macOS) --------------------------------------------

install_native_macos() {
    # The canasta-ansible name is pinned, not stale: it matches the
    # published image tag and the prefix on already-installed hosts.
    local install_dir="${PREFIX:-${HOME}/canasta-ansible}"

    need_cmd git
    check_python

    info "Installing Canasta (native mode, macOS)..."

    # Clone or update the repo (user-owned, no sudo needed for the repo),
    # then move to the selected channel.
    if [[ -d "${install_dir}/.git" ]]; then
        info "Updating existing installation at ${install_dir}..."
    else
        info "Cloning Canasta CLI to ${install_dir}..."
        git clone "$REPO_URL" "$install_dir"
    fi
    set_native_channel "$install_dir" ""

    # Create venv and install deps
    info "Creating Python virtual environment..."
    python3 -m venv "${install_dir}/.venv"
    "${install_dir}/.venv/bin/pip" install --quiet -r "${install_dir}/requirements.txt"

    # Build metadata
    info "Writing build metadata..."
    (cd "$install_dir" && git rev-parse --short HEAD > BUILD_COMMIT && git log -1 --format=%cd --date=format:'%Y-%m-%d %H:%M:%S' > BUILD_DATE)

    # Install Ansible collections
    if [[ -f "${install_dir}/requirements.yml" ]]; then
        info "Installing Ansible collections..."
        "${install_dir}/.venv/bin/ansible-galaxy" collection install \
            -r "${install_dir}/requirements.yml" 2>/dev/null || true
    fi

    # Symlinks need sudo for /usr/local/bin
    need_sudo
    info "Creating symlinks in ${BIN_DIR}..."
    $SUDO ln -sf "${install_dir}/canasta-native" "${BIN_DIR}/canasta-native"
    $SUDO ln -sf "${install_dir}/canasta-docker" "${BIN_DIR}/canasta-docker"
    $SUDO ln -sf "${BIN_DIR}/canasta-native" "${BIN_DIR}/canasta"
}

# --- Post-install check ------------------------------------------------------

post_install_summary() {
    local install_dir="$1"
    local platform="$2"

    info ""
    info "========================================"
    info "Canasta installed."
    info "  Install dir:    ${install_dir}"
    info "  canasta:        ${BIN_DIR}/canasta"

    local _optional=""
    if ! command -v git-crypt >/dev/null 2>&1; then
        _optional="${_optional}\n  canasta install git-crypt    (needed for gitops)"
    fi
    if ! command -v kubectl >/dev/null 2>&1; then
        _optional="${_optional}\n  canasta install k8s          (needed for Kubernetes)"
    fi
    if [[ -n "$_optional" ]]; then
        info ""
        info "Optional:"
        printf '%b\n' "$_optional"
    fi

    if [[ "$platform" == "linux" ]]; then
        info ""
        info "Log out and back in for group membership to take effect,"
        info "then run 'canasta doctor' to verify your setup."
    fi

    info "========================================"
}

# --- Main --------------------------------------------------------------------

main() {
    parse_args "$@"
    detect_platform
    detect_existing_install
    announce_upgrade

    case "$MODE" in
        docker)
            install_docker_mode
            ;;
        native)
            case "$PLATFORM" in
                linux)
                    install_native_linux
                    post_install_summary "/opt/canasta-ansible" "linux"
                    ;;
                macos)
                    install_native_macos
                    post_install_summary "${PREFIX:-${HOME}/canasta-ansible}" "macos"
                    ;;
            esac
            ;;
    esac

    post_install_upgrade
}

# When upgrading from the legacy Go CLI, the registered Canasta
# instances need their config-file migrations applied (e.g.
# hosts/hosts.yaml backfill for gitops) and their container images
# pulled to current. Both happen as part of `canasta upgrade`. Run
# it automatically here so users coming from `curl ... | bash` end
# up in a fully-migrated state without an extra manual step.
#
# Best-effort: if upgrade hits a transient issue (network, locked
# instance, etc.) we report a warning but don't fail the install —
# the CLI itself is in place and the user can re-run `canasta upgrade`
# after addressing the underlying problem.
post_install_upgrade() {
    if [[ "$EXISTING_INSTALL" != "go-cli" ]]; then
        return
    fi
    info ""
    info "========================================"
    info "Running 'canasta upgrade' to apply config migrations and"
    info "pull current container images for registered instances..."
    info "========================================"
    local rc=0
    if [[ "$DEV" == true ]]; then
        "${BIN_DIR}/canasta" upgrade --dev || rc=$?
    else
        "${BIN_DIR}/canasta" upgrade || rc=$?
    fi
    if [[ "$rc" -ne 0 ]]; then
        warn ""
        warn "'canasta upgrade' reported issues; review the output above."
        warn "The Canasta CLI itself is installed and ready. Re-run"
        warn "'canasta upgrade' after addressing the errors."
    fi
}

main "$@"
