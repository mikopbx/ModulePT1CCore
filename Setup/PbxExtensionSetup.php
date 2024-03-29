<?php
/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2023 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

/*
 * Copyright © MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Alexey Portnov, 9 2020
 */

namespace Modules\ModulePT1CCore\Setup;

use MikoPBX\Common\Models\PbxSettings;
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

    /**
     * Adds the module to the sidebar menu.
     * @see https://docs.mikopbx.com/mikopbx-development/module-developement/module-installer#addtosidebar
     *
     * @return bool The result of the addition process.
     */
    public function addToSidebar(): bool
    {
        $menuSettingsKey           = "AdditionalMenuItem{$this->moduleUniqueID}";
        $menuSettings              = PbxSettings::findFirstByKey($menuSettingsKey);
        if ($menuSettings === null) {
            $menuSettings      = new PbxSettings();
            $menuSettings->key = $menuSettingsKey;
        }
        $value               = [
            'uniqid'        => $this->moduleUniqueID,
            'group'         => 'integrations',
            'iconClass'     => 'puzzle',
            'caption'       => "Breadcrumb{$this->moduleUniqueID}",
            'showAtSidebar' => true,
        ];
        $menuSettings->value = json_encode($value);

        return $menuSettings->save();
    }
}
