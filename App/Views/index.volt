<form class="ui large grey segment form" id="module_pt1c_core-form">
    <div class="ui grey top right attached label" id="status">{{ t._("module_pt1c_coreDisconnected") }}</div>
    {{ form.render('id') }}
    <h3 class="ui header">{{ t._('module_pt1c_core_LinksRussian') }}</h3>
        <div class="ui bulleted link list">
            <a class="item" href="https://wiki.miko.ru/#s_i_telefonija_redakcija_1">{{ t._('module_pt1c_core_WikiDocsRussian') }}</a>
            <a class="item" href="https://www.youtube.com/watch?v=b447nuMLWGo">{{ t._('module_pt1c_core_WebinarLinkRussian') }}</a>
            <a class="item" href="https://telefon.miko.ru/forum/group8/" target="_blank">{{ t._('module_pt1c_core_Forum') }}</a>
        </div>
    {{ partial("partials/submitbutton",['indexurl':'pbx-extension-modules/index/']) }}
</form>
