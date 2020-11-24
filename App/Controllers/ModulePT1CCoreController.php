<?php
/**
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 11 2018
 */
namespace Modules\ModulePT1CCore\App\Controllers;
use MikoPBX\AdminCabinet\Controllers\BaseController;
use MikoPBX\Modules\PbxExtensionUtils;
use Modules\ModulePT1CCore\App\Forms\ModulePT1CCoreForm;
use Modules\ModulePT1CCore\Models\ModulePT1CCore;
use MikoPBX\Common\Models\Providers;

class ModulePT1CCoreController extends BaseController
{
    private $moduleUniqueID = 'ModulePT1CCore';
    private $moduleDir;

    /**
     * Basic initial class
     */
    public function initialize(): void
    {
        $this->moduleDir           = PbxExtensionUtils::getModuleDir($this->moduleUniqueID);
        $this->view->logoImagePath = "{$this->url->get()}assets/img/cache/{$this->moduleUniqueID}/logo.png";
        $this->view->submitMode    = null;
        parent::initialize();
    }

    /**
     * Index page controller
     */
    public function indexAction(): void
    {

        $footerCollection = $this->assets->collection('footerJS');
        $footerCollection->addJs('js/pbx/main/form.js', true);
        $footerCollection->addJs("js/cache/{$this->moduleUniqueID}/module_pt1c_core-index.js", true);

        $headerCollectionCSS = $this->assets->collection('headerCSS');
        $headerCollectionCSS->addCss("css/cache/{$this->moduleUniqueID}/module_pt1c_core.css", true);
        $headerCollectionCSS->addCss('css/vendor/semantic/list.min.css', true);

        $settings = ModulePT1CCore::findFirst();
        if ($settings === null) {
            $settings = new ModulePT1CCore();
        }

        $this->view->form = new ModulePT1CCoreForm($settings);
        $this->view->pick("{$this->moduleDir}/App/Views/index");
    }

    /**
     * Save settings AJAX action
     */
    public function saveAction() :void
    {
        if ( ! $this->request->isPost()) {
            return;
        }
        $data   = $this->request->getPost();
        $record = ModulePT1CCore::findFirst();

        if ($record === null) {
            $record = new ModulePT1CCore();
        }
        $this->db->begin();
        foreach ($record as $key => $value) {
            switch ($key) {
                case 'id':
                    break;
                case 'checkbox_field':
                case 'toggle_field':
                    if (array_key_exists($key, $data)) {
                        $record->$key = ($data[$key] === 'on') ? '1' : '0';
                    } else {
                        $record->$key = '0';
                    }
                    break;
                default:
                    if (array_key_exists($key, $data)) {
                        $record->$key = $data[$key];
                    } else {
                        $record->$key = '';
                    }
            }
        }

        if ($record->save() === FALSE) {
            $errors = $record->getMessages();
            $this->flash->error(implode('<br>', $errors));
            $this->view->success = false;
            $this->db->rollback();

            return;
        }

        $this->flash->success($this->translation->_('ms_SuccessfulSaved'));
        $this->view->success = true;
        $this->db->commit();
    }

}
