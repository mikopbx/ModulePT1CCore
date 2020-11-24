/*
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 11 2018
 *
 */

/* global globalRootUrl, globalTranslate, Form, Config */

const ModulePT1CCore = {
	$formObj: $('#module_pt1c_core-form'),
	$disabilityFields: $('#module_pt1c_core-form  .disability'),
	$statusToggle: $('#module-status-toggle'),
	$moduleStatus: $('#status'),
	/**
	 * On page load we init some Semantic UI library
	 */
	initialize() {
		// инициализируем чекбоксы и выподающие менюшки
		ModulePT1CCore.checkStatusToggle();
		window.addEventListener('ModuleStatusChanged', ModulePT1CCore.checkStatusToggle);
		ModulePT1CCore.initializeForm();
	},
	/**
	 * Change some form elements classes depends of module status
	 */
	checkStatusToggle() {
		if (ModulePT1CCore.$statusToggle.checkbox('is checked')) {
			ModulePT1CCore.$disabilityFields.removeClass('disabled');
			ModulePT1CCore.changeStatus('Connected');
			ModulePT1CCore.$moduleStatus.show();
		} else {
			ModulePT1CCore.$disabilityFields.addClass('disabled');
			ModulePT1CCore.$moduleStatus.hide();
		}
	},
	/**
	 * Send command to restart module workers after data changes,
	 * Also we can do it on TemplateConf->modelsEventChangeData method
	 */
	applyConfigurationChanges() {
		ModulePT1CCore.changeStatus('Updating');
		$.api({
			url: `${Config.pbxUrl}/pbxcore/api/modules/ModulePT1CCore/reload`,
			on: 'now',
			successTest: PbxApi.successTest,
			onSuccess() {
				ModulePT1CCore.changeStatus('Connected');
			},
			onFailure() {
				ModulePT1CCore.changeStatus('Disconnected');
			},
		});
	},
	/**
	 * We can modify some data before form send
	 * @param settings
	 * @returns {*}
	 */
	cbBeforeSendForm(settings) {
		const result = settings;
		result.data = ModulePT1CCore.$formObj.form('get values');
		return result;
	},
	/**
	 * Some actions after forms send
	 */
	cbAfterSendForm() {
		ModulePT1CCore.applyConfigurationChanges();
	},
	/**
	 * Initialize form parameters
	 */
	initializeForm() {
		Form.$formObj = ModulePT1CCore.$formObj;
		Form.url = `${globalRootUrl}ModulePT1CCore/save`;
		Form.validateRules = ModulePT1CCore.validateRules;
		Form.cbBeforeSendForm = ModulePT1CCore.cbBeforeSendForm;
		Form.cbAfterSendForm = ModulePT1CCore.cbAfterSendForm;
		Form.initialize();
	},
	/**
	 * Update the module state on form label
	 * @param status
	 */
	changeStatus(status) {
		switch (status) {
			case 'Connected':
				ModulePT1CCore.$moduleStatus
					.removeClass('grey')
					.removeClass('red')
					.addClass('green');
				ModulePT1CCore.$moduleStatus.html(globalTranslate.module_pt1c_coreConnected);
				break;
			case 'Disconnected':
				ModulePT1CCore.$moduleStatus
					.removeClass('green')
					.removeClass('red')
					.addClass('grey');
				ModulePT1CCore.$moduleStatus.html(globalTranslate.module_pt1c_coreDisconnected);
				break;
			case 'Updating':
				ModulePT1CCore.$moduleStatus
					.removeClass('green')
					.removeClass('red')
					.addClass('grey');
				ModulePT1CCore.$moduleStatus.html(`<i class="spinner loading icon"></i>${globalTranslate.module_pt1c_coreUpdateStatus}`);
				break;
			default:
				ModulePT1CCore.$moduleStatus
					.removeClass('green')
					.removeClass('red')
					.addClass('grey');
				ModulePT1CCore.$moduleStatus.html(globalTranslate.module_pt1c_coreDisconnected);
				break;
		}
	},
};

$(document).ready(() => {
	ModulePT1CCore.initialize();
});

