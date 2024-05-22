jQuery(function ($) {

    /**
     * This function is a setter and getter. On set it updates the browser history and returns the pathname + search
     * part of the URL. If no key is provided the function returns false. If a key with no value has been given, and
     * the query string parameter exists, the value is returned.
     *
     * @param key
     * @param value
     * @returns string|boolean false|pathname + query string
     */
    function mojQString(key, value) {
        var params = new URLSearchParams(window.location.search);

        if (!value && params.has(key)) {
            return params.get(key);
        }

        if (!key) {
            return false;
        }

        if (value) {
            params.set(key, value);
        } else {
            params.delete(key);
        }

        if (!window.history) {
            /* shhh */
        } else {
            let newUrl = `${location.pathname}?${params}`;
            // Remove the trailing '?' if no params are left
            if (newUrl.endsWith('?')) {
                newUrl = newUrl.slice(0, -1);
            }
            window.history.replaceState({}, '', newUrl);
        }

        return (window.location.pathname + window.location.search);
    }

    function setTab(tab) {
        var tabId, refererPath;

        if (!tab) {
            tab = $('.nav-tab-wrapper a').eq(0);
        } else {
            tab = $(".nav-tab-wrapper a[href='" + tab + "']");
        }

        if (!tab.attr('href')) {
            tab = $('.nav-tab-wrapper a').eq(0);
        }

        var href = tab.attr('href');

        if (!href || href.indexOf('#') === -1) {
            return false;
        }

        tabId = href.split('#')[1];

        if (!tabId) {
            return false; // If tabId is undefined, exit the function
        }

        tab.parent().find('a').removeClass('nav-tab-active');
        tab.addClass('nav-tab-active');

        $('.moj-component-settings-section').hide();
        $('div#' + tabId).fadeIn();

        // add to query string and update _wp_http_referer
        refererPath = mojQString('moj-tab', tabId);
        $('input[name="_wp_http_referer"]').val(refererPath);

        return false;
    }

    // Check if any class starting with hale-components_page_ is present on the body element
    function hasHaleComponentsClass() {
        return Array.from(document.body.classList).some(function (cls) {
            return cls.startsWith('hale-components_page_');
        });
    }

    // only run JS on our main settings page and not on the submenus
    if (!hasHaleComponentsClass()) {

        // only run JS on our main settings page and not on the submenus
        if ($('.toplevel_page_mojComponentSettings').length > 0) {


            $('.nav-tab-wrapper').on('click', 'a', function (e) {
                e.preventDefault();

                setTab($(this).attr('href'));
                return false;
            });

            // set the tab
            var mojTabSelected = mojQString('moj-tab');

            if (mojTabSelected) {
                setTab('#' + mojTabSelected);
            } else {
                setTab();
            }
        }
    }
});
