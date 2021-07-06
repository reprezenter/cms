/**
 * Description of cookiesPrivacyPolicy
 *
 * @author Maciej ĹosiĹski <maciek@w2b.pl>
 */

var docCookies = {
    getItem: function (sKey) {
        return unescape(document.cookie.replace(new RegExp("(?:(?:^|.*;\\s*)" + escape(sKey).replace(/[\-\.\+\*]/g, "\\$&") + "\\s*\\=\\s*((?:[^;](?!;))*[^;]?).*)|.*"), "$1")) || null;
    },
    setItem: function (sKey, sValue, vEnd, sPath, sDomain, bSecure) {
        if (!sKey || /^(?:expires|max\-age|path|domain|secure)$/i.test(sKey)) {
            return false;
        }
        var sExpires = "";
        if (vEnd) {
            switch (vEnd.constructor) {
                case Number:
                    sExpires = vEnd === Infinity ? "; expires=Fri, 31 Dec 9999 23:59:59 GMT" : "; max-age=" + vEnd;
                    break;
                case String:
                    sExpires = "; expires=" + vEnd;
                    break;
                case Date:
                    sExpires = "; expires=" + vEnd.toGMTString();
                    break;
            }
        }
        document.cookie = escape(sKey) + "=" + escape(sValue) + sExpires + (sDomain ? "; domain=" + sDomain : "") + (sPath ? "; path=" + sPath : "") + (bSecure ? "; secure" : "");
        return true;
    },
    removeItem: function (sKey, sPath) {
        if (!sKey || !this.hasItem(sKey)) {
            return false;
        }
        document.cookie = escape(sKey) + "=; expires=Thu, 01 Jan 1970 00:00:00 GMT" + (sPath ? "; path=" + sPath : "");
        return true;
    },
    hasItem: function (sKey) {
        return (new RegExp("(?:^|;\\s*)" + escape(sKey).replace(/[\-\.\+\*]/g, "\\$&") + "\\s*\\=")).test(document.cookie);
    },
    keys: /* optional method: you can safely remove it! */ function () {
        var aKeys = document.cookie.replace(/((?:^|\s*;)[^\=]+)(?=;|$)|^\s*|\s*(?:\=[^;]*)?(?:\1|$)/g, "").split(/\s*(?:\=[^;]*)?;\s*/);
        for (var nIdx = 0; nIdx < aKeys.length; nIdx++) {
            aKeys[nIdx] = unescape(aKeys[nIdx]);
        }
        return aKeys;
    }
};

var cookiesPrivacyPolicy = {
    init: function (options) {
        if (docCookies.hasItem('cookiesPrivacyPolicy')) {
            var cookieValue = docCookies.getItem('cookiesPrivacyPolicy');
            if (cookieValue) {
                return false;
            }
        }

        if (!options.container) {
            var container = document.body;
        } else {
            var container = document.getElementById(options.container);

            if (!container) {
                throw new Error('No such container');
            }
        }

        var placement = 'prepend';
        if (options.placement == 'append') {
            placement = 'append';
        }

        /*var css = document.createElement('div');
         css.innerHTML = this.getCss();
         container.appendChild(css);*/
        //dla ie, bo powyzsze w ie nie dziala
        //document.write(this.getCss(options.css));

        var cookiesPrivacyPolicyContainerWrapper = document.createElement('div');
        cookiesPrivacyPolicyContainerWrapper.id = 'cookiesPrivacyPolicyContainerWrapper';
        cookiesPrivacyPolicyContainerWrapper.innerHTML = this.getHtml(options.message);
        if (placement == 'append') {
            container.appendChild(cookiesPrivacyPolicyContainerWrapper);
        } else {
            container.insertBefore(cookiesPrivacyPolicyContainerWrapper, container.firstChild);
        }

        var closeBtn = document.getElementById('cookiesPrivacyPolicyCloseBtn');
        closeBtn.onclick = function () {
            cookiesPrivacyPolicyContainerWrapper.style.display = 'none';

            docCookies.setItem('cookiesPrivacyPolicy', 1, 60 * 60 * 24 * 366, '/');

            return false;
        };

        return true;
    },
    getHtml: function (message) {
        var html = '';

        html += '<div id="cookiesPrivacyPolicyContainer">';

        html += '<a href="#" id="cookiesPrivacyPolicyCloseBtn">x</a>';
        html += '<p>' + message + '</p>';

        html += '</div>';

        return html;
    },
    getCss: function (userCss) {
        css = '';
        css = '<style type="text/css">';
        css += '#cookiesPrivacyPolicyContainerWrapper{';
        css += 'width:100%;';
        css += 'box-sizing:border-box;';
        css += 'background:#000000;';                
        css += 'text-align:center';                
        css += '}';
        css += '#cookiesPrivacyPolicyContainer{';        
        css += 'padding:20px 0;';
        css += 'overflow:hidden;';
        css += 'margin:0 auto;';
        css += 'position:relative;';
        css += 'font-weight:normal;';
        css += '}';
        css += '#cookiesPrivacyPolicyContainer p{';
        css += 'padding-bottom:0;';
        css += 'vertical-align: middle;';        
        css += 'color:#ffffff;';
        css += '}';
        css += '#cookiesPrivacyPolicyContainer p a{';
        css += 'color:#0076a3;';
        css += '}';
        css += '#cookiesPrivacyPolicyContainer #cookiesPrivacyPolicyCloseBtn{';
        css += 'position:absolute; right:10px; top:10px;';
        css += 'color:#0076a3;';
        css += 'font-weight: bold;';
        css += 'font-size:16px;';
        css += 'width:20px;';
        css += 'height:20px;';
        css += 'line-height:16px;';
        css += 'vertical-align:center;';
        css += 'text-align:center;';
        css += 'display:inline-block;';        
        css += '}';
        css += '#cookiesPrivacyPolicyContainer #cookiesPrivacyPolicyCloseBtn:hover{';
        css += 'background: #0076a3;';
        css += 'color:#ffffff;';
        css += 'text-decoration:none;';
        css += '}';
        if (userCss) {
            for (var id in userCss) {
                css += id + '{';
                for (var i in userCss[id]) {
                    css += userCss[id][i];
                }
                css += '}';
            }
        }
        css += '</style>';
        return css;
    }
}