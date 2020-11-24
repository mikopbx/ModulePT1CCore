"use strict";

/*
 * Copyright (C) MIKO LLC - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Nikolay Beketov, 11 2018
 *
 */

/* global globalRootUrl, globalTranslate, Form, Config */
var ModulePT1CCore = {
  $formObj: $('#module_pt1c_core-form'),
  $disabilityFields: $('#module_pt1c_core-form  .disability'),
  $statusToggle: $('#module-status-toggle'),
  $moduleStatus: $('#status'),

  /**
   * On page load we init some Semantic UI library
   */
  initialize: function () {
    function initialize() {
      // инициализируем чекбоксы и выподающие менюшки
      ModulePT1CCore.checkStatusToggle();
      window.addEventListener('ModuleStatusChanged', ModulePT1CCore.checkStatusToggle);
      ModulePT1CCore.initializeForm();
    }

    return initialize;
  }(),

  /**
   * Change some form elements classes depends of module status
   */
  checkStatusToggle: function () {
    function checkStatusToggle() {
      if (ModulePT1CCore.$statusToggle.checkbox('is checked')) {
        ModulePT1CCore.$disabilityFields.removeClass('disabled');
        ModulePT1CCore.changeStatus('Connected');
        ModulePT1CCore.$moduleStatus.show();
      } else {
        ModulePT1CCore.$disabilityFields.addClass('disabled');
        ModulePT1CCore.$moduleStatus.hide();
      }
    }

    return checkStatusToggle;
  }(),

  /**
   * Send command to restart module workers after data changes,
   * Also we can do it on TemplateConf->modelsEventChangeData method
   */
  applyConfigurationChanges: function () {
    function applyConfigurationChanges() {
      ModulePT1CCore.changeStatus('Updating');
      $.api({
        url: "".concat(Config.pbxUrl, "/pbxcore/api/modules/ModulePT1CCore/reload"),
        on: 'now',
        successTest: PbxApi.successTest,
        onSuccess: function () {
          function onSuccess() {
            ModulePT1CCore.changeStatus('Connected');
          }

          return onSuccess;
        }(),
        onFailure: function () {
          function onFailure() {
            ModulePT1CCore.changeStatus('Disconnected');
          }

          return onFailure;
        }()
      });
    }

    return applyConfigurationChanges;
  }(),

  /**
   * We can modify some data before form send
   * @param settings
   * @returns {*}
   */
  cbBeforeSendForm: function () {
    function cbBeforeSendForm(settings) {
      var result = settings;
      result.data = ModulePT1CCore.$formObj.form('get values');
      return result;
    }

    return cbBeforeSendForm;
  }(),

  /**
   * Some actions after forms send
   */
  cbAfterSendForm: function () {
    function cbAfterSendForm() {
      ModulePT1CCore.applyConfigurationChanges();
    }

    return cbAfterSendForm;
  }(),

  /**
   * Initialize form parameters
   */
  initializeForm: function () {
    function initializeForm() {
      Form.$formObj = ModulePT1CCore.$formObj;
      Form.url = "".concat(globalRootUrl, "ModulePT1CCore/save");
      Form.validateRules = ModulePT1CCore.validateRules;
      Form.cbBeforeSendForm = ModulePT1CCore.cbBeforeSendForm;
      Form.cbAfterSendForm = ModulePT1CCore.cbAfterSendForm;
      Form.initialize();
    }

    return initializeForm;
  }(),

  /**
   * Update the module state on form label
   * @param status
   */
  changeStatus: function () {
    function changeStatus(status) {
      switch (status) {
        case 'Connected':
          ModulePT1CCore.$moduleStatus.removeClass('grey').removeClass('red').addClass('green');
          ModulePT1CCore.$moduleStatus.html(globalTranslate.module_pt1c_coreConnected);
          break;

        case 'Disconnected':
          ModulePT1CCore.$moduleStatus.removeClass('green').removeClass('red').addClass('grey');
          ModulePT1CCore.$moduleStatus.html(globalTranslate.module_pt1c_coreDisconnected);
          break;

        case 'Updating':
          ModulePT1CCore.$moduleStatus.removeClass('green').removeClass('red').addClass('grey');
          ModulePT1CCore.$moduleStatus.html("<i class=\"spinner loading icon\"></i>".concat(globalTranslate.module_pt1c_coreUpdateStatus));
          break;

        default:
          ModulePT1CCore.$moduleStatus.removeClass('green').removeClass('red').addClass('grey');
          ModulePT1CCore.$moduleStatus.html(globalTranslate.module_pt1c_coreDisconnected);
          break;
      }
    }

    return changeStatus;
  }()
};
$(document).ready(function () {
  ModulePT1CCore.initialize();
});
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VzIjpbInNyYy9tb2R1bGVfcHQxY19jb3JlLWluZGV4LmpzIl0sIm5hbWVzIjpbIk1vZHVsZVBUMUNDb3JlIiwiJGZvcm1PYmoiLCIkIiwiJGRpc2FiaWxpdHlGaWVsZHMiLCIkc3RhdHVzVG9nZ2xlIiwiJG1vZHVsZVN0YXR1cyIsImluaXRpYWxpemUiLCJjaGVja1N0YXR1c1RvZ2dsZSIsIndpbmRvdyIsImFkZEV2ZW50TGlzdGVuZXIiLCJpbml0aWFsaXplRm9ybSIsImNoZWNrYm94IiwicmVtb3ZlQ2xhc3MiLCJjaGFuZ2VTdGF0dXMiLCJzaG93IiwiYWRkQ2xhc3MiLCJoaWRlIiwiYXBwbHlDb25maWd1cmF0aW9uQ2hhbmdlcyIsImFwaSIsInVybCIsIkNvbmZpZyIsInBieFVybCIsIm9uIiwic3VjY2Vzc1Rlc3QiLCJQYnhBcGkiLCJvblN1Y2Nlc3MiLCJvbkZhaWx1cmUiLCJjYkJlZm9yZVNlbmRGb3JtIiwic2V0dGluZ3MiLCJyZXN1bHQiLCJkYXRhIiwiZm9ybSIsImNiQWZ0ZXJTZW5kRm9ybSIsIkZvcm0iLCJnbG9iYWxSb290VXJsIiwidmFsaWRhdGVSdWxlcyIsInN0YXR1cyIsImh0bWwiLCJnbG9iYWxUcmFuc2xhdGUiLCJtb2R1bGVfcHQxY19jb3JlQ29ubmVjdGVkIiwibW9kdWxlX3B0MWNfY29yZURpc2Nvbm5lY3RlZCIsIm1vZHVsZV9wdDFjX2NvcmVVcGRhdGVTdGF0dXMiLCJkb2N1bWVudCIsInJlYWR5Il0sIm1hcHBpbmdzIjoiOztBQUFBOzs7Ozs7OztBQVFBO0FBRUEsSUFBTUEsY0FBYyxHQUFHO0FBQ3RCQyxFQUFBQSxRQUFRLEVBQUVDLENBQUMsQ0FBQyx3QkFBRCxDQURXO0FBRXRCQyxFQUFBQSxpQkFBaUIsRUFBRUQsQ0FBQyxDQUFDLHFDQUFELENBRkU7QUFHdEJFLEVBQUFBLGFBQWEsRUFBRUYsQ0FBQyxDQUFDLHVCQUFELENBSE07QUFJdEJHLEVBQUFBLGFBQWEsRUFBRUgsQ0FBQyxDQUFDLFNBQUQsQ0FKTTs7QUFLdEI7OztB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LFVBQUFBLGNBQWMsQ0FBQ0ssYUFBZixDQUE2QmdDLElBQTdCLENBQWtDQyxlQUFlLENBQUNDLHlCQUFsRDtBQUNBOztBQUNELGFBQUssY0FBTDtBQUNDdkMsVUFBQUEsY0FBYyxDQUFDSyxhQUFmLENBQ0VPLFdBREYsQ0FDYyxPQURkLEVBRUVBLFdBRkYsQ0FFYyxLQUZkLEVBR0VHLFFBSEYsQ0FHVyxNQUhYO0FBSUFmLFVBQUFBLGNBQWMsQ0FBQ0ssYUFBZixDQUE2QmdDLElBQTdCLENBQWtDQyxlQUFlLENBQUNFLDRCQUFsRDtBQUNBOztBQUNELGFBQUssVUFBTDtBQUNDeEMsVUFBQUEsY0FBYyxDQUFDSyxhQUFmLENBQ0VPLFdBREYsQ0FDYyxPQURkLEVBRUVBLFdBRkYsQ0FFYyxLQUZkLEVBR0VHLFFBSEYsQ0FHVyxNQUhYO0FBSUFmLFVBQUFBLGNBQWMsQ0FBQ0ssYUFBZixDQUE2QmdDLElBQTdCLGlEQUF5RUMsZUFBZSxDQUFDRyw0QkFBekY7QUFDQTs7QUFDRDtBQUNDekMsVUFBQUEsY0FBYyxDQUFDSyxhQUFmLENBQ0VPLFdBREYsQ0FDYyxPQURkLEVBRUVBLFdBRkYsQ0FFYyxLQUZkLEVBR0VHLFFBSEYsQ0FHVyxNQUhYO0FBSUFmLFVBQUFBLGNBQWMsQ0FBQ0ssYUFBZixDQUE2QmdDLElBQTdCLENBQWtDQyxlQUFlLENBQUNFLDRCQUFsRDtBQUNBO0FBNUJGO0FBOEJBOztBQTNHcUI7QUFBQTtBQUFBLENBQXZCO0FBOEdBdEMsQ0FBQyxDQUFDd0MsUUFBRCxDQUFELENBQVlDLEtBQVosQ0FBa0IsWUFBTTtBQUN2QjNDLEVBQUFBLGNBQWMsQ0FBQ00sVUFBZjtBQUNBLENBRkQiLCJzb3VyY2VzQ29udGVudCI6WyIvKlxuICogQ29weXJpZ2h0IChDKSBNSUtPIExMQyAtIEFsbCBSaWdodHMgUmVzZXJ2ZWRcbiAqIFVuYXV0aG9yaXplZCBjb3B5aW5nIG9mIHRoaXMgZmlsZSwgdmlhIGFueSBtZWRpdW0gaXMgc3RyaWN0bHkgcHJvaGliaXRlZFxuICogUHJvcHJpZXRhcnkgYW5kIGNvbmZpZGVudGlhbFxuICogV3JpdHRlbiBieSBOaWtvbGF5IEJla2V0b3YsIDExIDIwMThcbiAqXG4gKi9cblxuLyogZ2xvYmFsIGdsb2JhbFJvb3RVcmwsIGdsb2JhbFRyYW5zbGF0ZSwgRm9ybSwgQ29uZmlnICovXG5cbmNvbnN0IE1vZHVsZVBUMUNDb3JlID0ge1xuXHQkZm9ybU9iajogJCgnI21vZHVsZV9wdDFjX2NvcmUtZm9ybScpLFxuXHQkZGlzYWJpbGl0eUZpZWxkczogJCgnI21vZHVsZV9wdDFjX2NvcmUtZm9ybSAgLmRpc2FiaWxpdHknKSxcblx0JHN0YXR1c1RvZ2dsZTogJCgnI21vZHVsZS1zdGF0dXMtdG9nZ2xlJyksXG5cdCRtb2R1bGVTdGF0dXM6ICQoJyNzdGF0dXMnKSxcblx0LyoqXG5cdCAqIE9uIHBhZ2UgbG9hZCB3ZSBpbml0IHNvbWUgU2VtYW50aWMgVUkgbGlicmFyeVxuXHQgKi9cblx0aW5pdGlhbGl6ZSgpIHtcblx0XHQvLyDQuNC90LjRhtC40LDQu9C40LfQuNGA0YPQtdC8INGH0LXQutCx0L7QutGB0Ysg0Lgg0LLRi9C/0L7QtNCw0Y7RidC40LUg0LzQtdC90Y7RiNC60Lhcblx0XHRNb2R1bGVQVDFDQ29yZS5jaGVja1N0YXR1c1RvZ2dsZSgpO1xuXHRcdHdpbmRvdy5hZGRFdmVudExpc3RlbmVyKCdNb2R1bGVTdGF0dXNDaGFuZ2VkJywgTW9kdWxlUFQxQ0NvcmUuY2hlY2tTdGF0dXNUb2dnbGUpO1xuXHRcdE1vZHVsZVBUMUNDb3JlLmluaXRpYWxpemVGb3JtKCk7XG5cdH0sXG5cdC8qKlxuXHQgKiBDaGFuZ2Ugc29tZSBmb3JtIGVsZW1lbnRzIGNsYXNzZXMgZGVwZW5kcyBvZiBtb2R1bGUgc3RhdHVzXG5cdCAqL1xuXHRjaGVja1N0YXR1c1RvZ2dsZSgpIHtcblx0XHRpZiAoTW9kdWxlUFQxQ0NvcmUuJHN0YXR1c1RvZ2dsZS5jaGVja2JveCgnaXMgY2hlY2tlZCcpKSB7XG5cdFx0XHRNb2R1bGVQVDFDQ29yZS4kZGlzYWJpbGl0eUZpZWxkcy5yZW1vdmVDbGFzcygnZGlzYWJsZWQnKTtcblx0XHRcdE1vZHVsZVBUMUNDb3JlLmNoYW5nZVN0YXR1cygnQ29ubmVjdGVkJyk7XG5cdFx0XHRNb2R1bGVQVDFDQ29yZS4kbW9kdWxlU3RhdHVzLnNob3coKTtcblx0XHR9IGVsc2Uge1xuXHRcdFx0TW9kdWxlUFQxQ0NvcmUuJGRpc2FiaWxpdHlGaWVsZHMuYWRkQ2xhc3MoJ2Rpc2FibGVkJyk7XG5cdFx0XHRNb2R1bGVQVDFDQ29yZS4kbW9kdWxlU3RhdHVzLmhpZGUoKTtcblx0XHR9XG5cdH0sXG5cdC8qKlxuXHQgKiBTZW5kIGNvbW1hbmQgdG8gcmVzdGFydCBtb2R1bGUgd29ya2VycyBhZnRlciBkYXRhIGNoYW5nZXMsXG5cdCAqIEFsc28gd2UgY2FuIGRvIGl0IG9uIFRlbXBsYXRlQ29uZi0+bW9kZWxzRXZlbnRDaGFuZ2VEYXRhIG1ldGhvZFxuXHQgKi9cblx0YXBwbHlDb25maWd1cmF0aW9uQ2hhbmdlcygpIHtcblx0XHRNb2R1bGVQVDFDQ29yZS5jaGFuZ2VTdGF0dXMoJ1VwZGF0aW5nJyk7XG5cdFx0JC5hcGkoe1xuXHRcdFx0dXJsOiBgJHtDb25maWcucGJ4VXJsfS9wYnhjb3JlL2FwaS9tb2R1bGVzL01vZHVsZVBUMUNDb3JlL3JlbG9hZGAsXG5cdFx0XHRvbjogJ25vdycsXG5cdFx0XHRzdWNjZXNzVGVzdDogUGJ4QXBpLnN1Y2Nlc3NUZXN0LFxuXHRcdFx0b25TdWNjZXNzKCkge1xuXHRcdFx0XHRNb2R1bGVQVDFDQ29yZS5jaGFuZ2VTdGF0dXMoJ0Nvbm5lY3RlZCcpO1xuXHRcdFx0fSxcblx0XHRcdG9uRmFpbHVyZSgpIHtcblx0XHRcdFx0TW9kdWxlUFQxQ0NvcmUuY2hhbmdlU3RhdHVzKCdEaXNjb25uZWN0ZWQnKTtcblx0XHRcdH0sXG5cdFx0fSk7XG5cdH0sXG5cdC8qKlxuXHQgKiBXZSBjYW4gbW9kaWZ5IHNvbWUgZGF0YSBiZWZvcmUgZm9ybSBzZW5kXG5cdCAqIEBwYXJhbSBzZXR0aW5nc1xuXHQgKiBAcmV0dXJucyB7Kn1cblx0ICovXG5cdGNiQmVmb3JlU2VuZEZvcm0oc2V0dGluZ3MpIHtcblx0XHRjb25zdCByZXN1bHQgPSBzZXR0aW5ncztcblx0XHRyZXN1bHQuZGF0YSA9IE1vZHVsZVBUMUNDb3JlLiRmb3JtT2JqLmZvcm0oJ2dldCB2YWx1ZXMnKTtcblx0XHRyZXR1cm4gcmVzdWx0O1xuXHR9LFxuXHQvKipcblx0ICogU29tZSBhY3Rpb25zIGFmdGVyIGZvcm1zIHNlbmRcblx0ICovXG5cdGNiQWZ0ZXJTZW5kRm9ybSgpIHtcblx0XHRNb2R1bGVQVDFDQ29yZS5hcHBseUNvbmZpZ3VyYXRpb25DaGFuZ2VzKCk7XG5cdH0sXG5cdC8qKlxuXHQgKiBJbml0aWFsaXplIGZvcm0gcGFyYW1ldGVyc1xuXHQgKi9cblx0aW5pdGlhbGl6ZUZvcm0oKSB7XG5cdFx0Rm9ybS4kZm9ybU9iaiA9IE1vZHVsZVBUMUNDb3JlLiRmb3JtT2JqO1xuXHRcdEZvcm0udXJsID0gYCR7Z2xvYmFsUm9vdFVybH1Nb2R1bGVQVDFDQ29yZS9zYXZlYDtcblx0XHRGb3JtLnZhbGlkYXRlUnVsZXMgPSBNb2R1bGVQVDFDQ29yZS52YWxpZGF0ZVJ1bGVzO1xuXHRcdEZvcm0uY2JCZWZvcmVTZW5kRm9ybSA9IE1vZHVsZVBUMUNDb3JlLmNiQmVmb3JlU2VuZEZvcm07XG5cdFx0Rm9ybS5jYkFmdGVyU2VuZEZvcm0gPSBNb2R1bGVQVDFDQ29yZS5jYkFmdGVyU2VuZEZvcm07XG5cdFx0Rm9ybS5pbml0aWFsaXplKCk7XG5cdH0sXG5cdC8qKlxuXHQgKiBVcGRhdGUgdGhlIG1vZHVsZSBzdGF0ZSBvbiBmb3JtIGxhYmVsXG5cdCAqIEBwYXJhbSBzdGF0dXNcblx0ICovXG5cdGNoYW5nZVN0YXR1cyhzdGF0dXMpIHtcblx0XHRzd2l0Y2ggKHN0YXR1cykge1xuXHRcdFx0Y2FzZSAnQ29ubmVjdGVkJzpcblx0XHRcdFx0TW9kdWxlUFQxQ0NvcmUuJG1vZHVsZVN0YXR1c1xuXHRcdFx0XHRcdC5yZW1vdmVDbGFzcygnZ3JleScpXG5cdFx0XHRcdFx0LnJlbW92ZUNsYXNzKCdyZWQnKVxuXHRcdFx0XHRcdC5hZGRDbGFzcygnZ3JlZW4nKTtcblx0XHRcdFx0TW9kdWxlUFQxQ0NvcmUuJG1vZHVsZVN0YXR1cy5odG1sKGdsb2JhbFRyYW5zbGF0ZS5tb2R1bGVfcHQxY19jb3JlQ29ubmVjdGVkKTtcblx0XHRcdFx0YnJlYWs7XG5cdFx0XHRjYXNlICdEaXNjb25uZWN0ZWQnOlxuXHRcdFx0XHRNb2R1bGVQVDFDQ29yZS4kbW9kdWxlU3RhdHVzXG5cdFx0XHRcdFx0LnJlbW92ZUNsYXNzKCdncmVlbicpXG5cdFx0XHRcdFx0LnJlbW92ZUNsYXNzKCdyZWQnKVxuXHRcdFx0XHRcdC5hZGRDbGFzcygnZ3JleScpO1xuXHRcdFx0XHRNb2R1bGVQVDFDQ29yZS4kbW9kdWxlU3RhdHVzLmh0bWwoZ2xvYmFsVHJhbnNsYXRlLm1vZHVsZV9wdDFjX2NvcmVEaXNjb25uZWN0ZWQpO1xuXHRcdFx0XHRicmVhaztcblx0XHRcdGNhc2UgJ1VwZGF0aW5nJzpcblx0XHRcdFx0TW9kdWxlUFQxQ0NvcmUuJG1vZHVsZVN0YXR1c1xuXHRcdFx0XHRcdC5yZW1vdmVDbGFzcygnZ3JlZW4nKVxuXHRcdFx0XHRcdC5yZW1vdmVDbGFzcygncmVkJylcblx0XHRcdFx0XHQuYWRkQ2xhc3MoJ2dyZXknKTtcblx0XHRcdFx0TW9kdWxlUFQxQ0NvcmUuJG1vZHVsZVN0YXR1cy5odG1sKGA8aSBjbGFzcz1cInNwaW5uZXIgbG9hZGluZyBpY29uXCI+PC9pPiR7Z2xvYmFsVHJhbnNsYXRlLm1vZHVsZV9wdDFjX2NvcmVVcGRhdGVTdGF0dXN9YCk7XG5cdFx0XHRcdGJyZWFrO1xuXHRcdFx0ZGVmYXVsdDpcblx0XHRcdFx0TW9kdWxlUFQxQ0NvcmUuJG1vZHVsZVN0YXR1c1xuXHRcdFx0XHRcdC5yZW1vdmVDbGFzcygnZ3JlZW4nKVxuXHRcdFx0XHRcdC5yZW1vdmVDbGFzcygncmVkJylcblx0XHRcdFx0XHQuYWRkQ2xhc3MoJ2dyZXknKTtcblx0XHRcdFx0TW9kdWxlUFQxQ0NvcmUuJG1vZHVsZVN0YXR1cy5odG1sKGdsb2JhbFRyYW5zbGF0ZS5tb2R1bGVfcHQxY19jb3JlRGlzY29ubmVjdGVkKTtcblx0XHRcdFx0YnJlYWs7XG5cdFx0fVxuXHR9LFxufTtcblxuJChkb2N1bWVudCkucmVhZHkoKCkgPT4ge1xuXHRNb2R1bGVQVDFDQ29yZS5pbml0aWFsaXplKCk7XG59KTtcblxuIl19