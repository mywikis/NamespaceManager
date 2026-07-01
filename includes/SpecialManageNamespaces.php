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
		$out->setPageTitle( $this->msg( 'managenamespaces-title' )->text() );
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

        $out->addHTML(new OOUI\FormLayout([
            'method' => 'POST',
            'action' => 'Special:ManageNamespaces',
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
                            ]
                        ),
                        new OOUI\FieldLayout(
                            new OOUI\ButtonInputWidget([
                                'name' => 'save',
                                'label' => 'Save JSON',
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
