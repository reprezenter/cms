var rwd = {
    init: function () {
        var hamburger = document.getElementsByClassName('hamburger');
        if (hamburger.length != 1) {
            return false;
        }
        hamburger[0].addEventListener('click', function (e) {
            var menu = document.getElementsByClassName('menu')[0];
            if (menu.style.display === 'block') {
                menu.style.display = 'none';
            } else {
                menu.style.display = 'block';
            }

        });
    }
}
function r(f) {
    /in/.test(document.readyState) ? setTimeout('r(' + f + ')', 9) : f()
}
r(function () {
    rwd.init();
        document.getElementsByClassName('banner')[0].style.background = "url('/img/banner.jpg') top center no-repeat";
});