<?php
/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 9 2020
 */

namespace Modules\ModulePT1CCore\Setup;

use MikoPBX\Common\Models\Extensions;
use MikoPBX\Modules\Setup\PbxExtensionSetupBase;
use Modules\ModulePT1CCore\Models\ModulePT1CCore;


/**
 * Class PbxExtensionSetup
 * Module installer and uninstaller
 *
 * @package Modules\ModulePT1CCore\Setup
 */
class PbxExtensionSetup extends PbxExtensionSetupBase
{
    /**
     * Creates database structure according to models annotations
     *
     * If it necessary, it fills some default settings, and change sidebar menu item representation for this module
     *
     * After installation it registers module on PbxExtensionModules model
     *
     *
     * @return bool result of installation
     */
    public function installDB(): bool
    {
        $result = $this->createSettingsTableByModelsAnnotations();

        if ($result) {
            $settings = ModulePT1CCore::findFirst();
            if ( $settings === null) {
                $settings = new ModulePT1CCore();
                $settings->save();
            }
        }

        if ($result) {
            $result = $this->registerNewModule();
        }

        $this->addToSidebar();

        return $result;
    }

    /**
     * Create folders on PBX system and apply rights
     *
     * @return bool result of installation
     */
    public function installFiles(): bool
    {
        return parent::installFiles();
    }

    /**
     * Unregister module on PbxExtensionModules,
     * Makes data backup if $keepSettings is true
     *
     * Before delete module we can do some soft delete changes, f.e. change forwarding rules i.e.
     *
     * @param  $keepSettings bool creates backup folder with module settings
     *
     * @return bool uninstall result
     */
    public function unInstallDB($keepSettings = false): bool
    {
        $result = true;
        $settings = ModulePT1CCore::findFirst();
        if ($settings !== null){
            $result = $settings->delete();
        }
        if ($result){
            $result = parent::unInstallDB($keepSettings);
        } else {
            $this->messages[] = 'Delete module extension failure: '.$settings->getMessages();
        }
        return $result;
    }
}
