/* global Statamic, translate */
/* jshint devel:true */

// Injects a 'Spam' button to the DOM
let addButtons = function( delay ) {
    ( function( $ ) {

        // label to use for the duplicate button - should be translatable really
        //let label = translate('addons.Duplicate::settings.duplicate');

       // get the form, it's the last segment
        let form = location.pathname.split('/');
        form = form[form.length - 1].toString();

        // We need to run this code after everything else has finished executing,
        // so call via an anonymous function, with setTimeout
        window.setTimeout( function() {

            $('td.column-actions ul.dropdown-menu').each( function() {

                // get segments of the url so we can get the id
                let segments = $(this)
                    .parents('td.column-actions')
                    .siblings('td.cell-datestring')
                    .children('a')
                    .attr('href')
                    .split('/');

                // get the id, it's the last segment
                let id = segments[segments.length - 1].toString();

                let href = '/!/Akismet/submit_spam?form=' + form + '&id=' + id;

                // // create an element to add
                let li = $('<li><a href="' + href + '">Spam</a></li>');
                //
                // add to the DOM
                $(this).children('li').eq(0).after( li );

            });

            // delay will be 0 if the browser supports XMLHttpRequest, otherwise an arbitrary period to wait
        }, delay );

    })( jQuery );
};

// Run the function, based on whether the browser supports XMLHttpRequest.prototype.open or not
if( typeof XMLHttpRequest.prototype.open ===  'function' ) {

    // Hijack the XMLHttpRequest.prototype.open function, and listen for any XHR `load` events
    // Listen for GET requests to `/get` (which loads entries) and then call `addButtons`
    ( function( open ) {

        XMLHttpRequest.prototype.open = function( method, url, async, user, pass ) {

            this.addEventListener( 'load', function() {

                // if we've just loaded some submissions, then inject the `duplicate` buttons
                if( method.toUpperCase() === 'GET' ) {

                    // `delay`, will be 0 – as this will only be run after the entries have loaded
                    addButtons( 0 );
                }

            }, false );

            // continue with the request
            open.call( this, method, url, async, user, pass );
        };

    })( XMLHttpRequest.prototype.open );

// older browser, so just call the function after the window has loaded, and allow a delay for
// the entries to load – guess 1 second. This can be made higher if the entries are not loading in time
} else {

    window.addEventListener( 'load', function() {

        addButtons( 1000 );

    });

}