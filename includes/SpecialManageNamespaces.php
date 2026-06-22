<?php
/**
 * ManageNamespaces special page
 *
 * @file SpecialManageNamespaces.php
 * @ingroup Extensions
 */
class SpecialManageNamespaces extends SpecialPage {
	public function __construct() {
		parent::__construct( 'ManageNamespaces', 'managenamespaces' );
	}

	/**
	 * Show the page to the user
	 *
	 * @param string $sub The subpage string argument (if any).
	 */
	public function execute( $sub ) {
		global $wgDBname;

		$this->checkPermissions();
		
		$out = $this->getOutput();
		$out->enableOOUI();
		$out->addModules( 'ext.namespaceManager' );
		$out->setPageTitle( $this->msg( 'managenamespaces-title' ) );
		$out->addWikiMsg( 'managenamespaces-intro' );

        $request = $this->getRequest();
        $namespaceJsonContents = $request->getText('namespaceJsonContents');        

        if (!empty($namespaceJsonContents)) {
            $textWidgetContents = $namespaceJsonContents;
            $status = NamespaceManager::saveNamespaceDataRaw($namespaceJsonContents);
            if ($status === false) {
                $out->addHTML(new OOUI\MessageWidget([
                    'type' => 'error',
                    'label' => 'The file could not be saved. Check if your syntax is correct.',
                ]));
            } else {
                $out->addHTML(new OOUI\MessageWidget([
                    'type' => 'success',
                    'label' => 'JSON configuration file updated.',
                ]));
            }
        } else {
            $textWidgetContents = NamespaceManager::loadNamespaceDataRaw();
        }

        // Provide the current namespace definitions to the frontend interface so
        // it can render the dynamic management UI. Falls back to an empty list if
        // the stored file is missing or contains invalid JSON.
        $namespaceData = json_decode($textWidgetContents !== false ? $textWidgetContents : '', true);
        if (!is_array($namespaceData)) {
            $namespaceData = [];
        }
        $out->addJsConfigVars('wgNamespaceManagerData', $namespaceData);

        // Container the JavaScript interface renders into. When JavaScript is
        // unavailable, this stays empty and the raw JSON editor below is used.
        $out->addHTML(Html::element('div', ['id' => 'namespacemanager-app']));

        $out->addHTML(new OOUI\FormLayout([
            'method' => 'POST',
            'action' => 'Special:ManageNamespaces',
            'id' => 'namespacemanager-form',
            'items' => [
                new OOUI\FieldsetLayout([
                    'label' => 'Namespaces definition',
                    'items' => [
                        new OOUI\FieldLayout(
                            new OOUI\MultilineTextInputWidget([
                                'rows' => 60,
                                'name' => 'namespaceJsonContents',
                                'value' => $textWidgetContents
                            ]),
                            [
                                'label' => 'JSON file contents',
                                'align' => 'top',
                                'classes' => ['namespacemanager-rawjson'],
                            ]
                        ),
                        new OOUI\FieldLayout(
                            new OOUI\ButtonInputWidget([
                                'name' => 'save',
                                'label' => 'Save namespaces',
                                'type' => 'submit',
                                'flags' => ['primary', 'progressive'],
                                'icon' => 'check',
                            ]),
                            [
                                'label' => null,
                                'align' => 'top',
                            ]
                        ),
                    ]
                ])
            ]
        ]));
	}

	protected function getGroupName() {
		return 'other';
	}
}
