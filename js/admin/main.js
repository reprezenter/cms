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
};
var formUploader = {
    form: null,
    init: function () {

        this.form = $('.imageGallery').parent('form');
        var self = this;
        if (!this.form.length) {
            return false;
        }

        $('.imageGallery img').click(function () {
            $('label[for="blog_form_image"]').trigger('click');
        });
        var inputfiles = this.form.find('input.uploader');
        inputfiles.each(function (index) {
            var inputfile = $(this);
            var gallery = inputfile.parent('.formElement').next('.imageGallery');
            if (window.FormData) {
                formdata = new FormData();
                self.updateGallery(formdata, inputfile.attr('data-url'), gallery);
            }

            if (inputfile[0].addEventListener) {
                inputfile[0].addEventListener("change", function (evt) {
                    var i = 0, len = this.files.length, img, reader, file;
                    var $this = $(this);
                    if (len) {
                        gallery.html('<img class="uploadedBtn" src="/img/admin/uploaded.svg" /><img class="galleryLoadig" src="/common/images/loading.gif" />');
                        $('.imageGallery img').unbind('click').click(function () {
                            $('label[for="blog_form_image"]').trigger('click');
                        });
                    }
                    for (; i < len; i++) {
                        file = this.files[i];
                        if (!!file.type.match(/image.*/) || file.type === 'application/pdf') {
                            if (formdata) {
                                formdata = new FormData();
                                formdata.append("images[]", file);
                                //$this.parent('.formElement').find('label').text(file.name);
                            }
                            if (formdata) {
                                $.ajax({
                                    url: inputfile.attr('data-url'),
                                    type: "POST",
                                    data: formdata,
                                    processData: false,
                                    contentType: false,
                                    success: function (response) {
                                        $('.galleryLoadig').hide();
                                        self.updateGallery(formdata, inputfile.attr('data-url'), gallery);
                                    }
                                });
                            }
                        }
                    }

                }, false);
            }
        });
        return true;
    },
    updateGallery: function (formdata, url, gallery) {
        $.ajax({
            url: url,
            type: "POST",
            data: formdata,
            processData: false,
            contentType: false,
            success: function (_response) {
                const response = JSON.parse(_response);;
                $.each(response, function (index, value) {
                    if (value.length > 0) {
                        gallery.html('<img class="uploadedBtn" src="/img/admin/uploaded.svg" />');
                        $('.imageGallery img').unbind('click').click(function () {
                            $('label[for="blog_form_image"]').trigger('click');
                        });
                    }
                    $.each(value, function (i, v) {                       ;
                        gallery.append('<img src="' + v.src + '" /><a href="" class="deleteImg" data-id="' + v.id + '" data-file="' + v.file + '" data-e_id="' + v.e_id + '" data-delete-url="' + v.deleteUrl + '" ><img src="/img/admin/close-btn.png"/></a>');
                    });
                });
                $('.deleteImg').unbind('click');
                $('.deleteImg').click(function (e) {
                    e.preventDefault();
                    var url = $(this).attr('data-delete-url');
                    var file = $(this).attr('data-file');
                    var id = $(this).attr('data-id');
                    var e_id = $(this).attr('data-e_id');
                    var $self = $(this);
                    $.ajax({
                        url: url,
                        type: "POST",
                        data: {file: file, id: id, del: 1, e_id: e_id},
                        success: function () {
                            $self.hide();
                            $self.prev('img').hide();
                            $('.uploadedBtn').attr('src', '/img/admin/upload.svg');
                            return false;
                        }
                    });
                });
                return false;
            }
        });
    }

};
function r(f) {
    /in/.test(document.readyState) ? setTimeout('r(' + f + ')', 9) : f()
}
r(function () {
    rwd.init();      
    formUploader.init();
});