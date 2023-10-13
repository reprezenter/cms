const useDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
const isSmallScreen = window.matchMedia('(max-width: 1023.5px)').matches;

tinymce.init({
    selector: 'textarea.wyswig',
    plugins: 'preview importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media template codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help charmap quickbars emoticons accordion',
    editimage_cors_hosts: ['picsum.photos'],
    menubar: 'file edit view insert format tools table help',
    toolbar: "undo redo | accordion accordionremove | blocks fontfamily fontsize | bold italic underline strikethrough | align numlist bullist | link image | table media | lineheight outdent indent| forecolor backcolor removeformat | charmap emoticons | code fullscreen preview | save print | pagebreak anchor codesample | ltr rtl",
    autosave_ask_before_unload: true,
    autosave_interval: '30s',
    autosave_prefix: '{path}{query}-{id}-',
    autosave_restore_when_empty: false,
    autosave_retention: '2m',
    image_advtab: true,
    file_picker_types: 'image',
    importcss_append: true,
    /* and here's our custom image picker*/
    file_picker_callback: (cb, value, meta) => {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/*');

        input.addEventListener('change', (e) => {
            const file = e.target.files[0];

            const reader = new FileReader();
            reader.addEventListener('load', () => {
                /*
                 Note: Now we need to register the blob in TinyMCEs image blob
                 registry. In the next release this part hopefully won't be
                 necessary, as we are looking to handle it internally.
                 */
                const id = 'blobid' + (new Date()).getTime();
                const blobCache = tinymce.activeEditor.editorUpload.blobCache;
                const base64 = reader.result.split(',')[1];
                const blobInfo = blobCache.create(id, file, base64);
                blobCache.add(blobInfo);

                /* call the callback and populate the Title field with the file name */
                cb(blobInfo.blobUri(), {title: file.name});
            });
            reader.readAsDataURL(file);
        });

        input.click();
    },
    templates: [
        {title: 'New Table', description: 'creates a new table', content: '<div class="mceTmpl"><table width="98%%"  border="0" cellspacing="0" cellpadding="0"><tr><th scope="col"> </th><th scope="col"> </th></tr><tr><td> </td><td> </td></tr></table></div>'},
        {title: 'Starting my story', description: 'A cure for writers block', content: 'Once upon a time...'},
        {title: 'New list with dates', description: 'New List with dates', content: '<div class="mceTmpl"><span class="cdate">cdate</span><br><span class="mdate">mdate</span><h2>My List</h2><ul><li></li><li></li></ul></div>'}
    ],
    template_cdate_format: '[Date Created (CDATE): %m/%d/%Y : %H:%M:%S]',
    template_mdate_format: '[Date Modified (MDATE): %m/%d/%Y : %H:%M:%S]',
    height: 600,
    image_caption: true,
    quickbars_selection_toolbar: 'bold italic | quicklink h2 h3 blockquote quickimage quicktable',
    noneditable_class: 'mceNonEditable',
    toolbar_mode: 'sliding',
    contextmenu: 'link image table',
    skin: useDarkMode ? 'oxide-dark' : 'oxide',
    content_css: useDarkMode ? 'dark' : 'default',
    content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:16px }'
});

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
                const response = JSON.parse(_response);
                ;
                $.each(response, function (index, value) {
                    if (value.length > 0) {
                        gallery.html('<img class="uploadedBtn" src="/img/admin/uploaded.svg" />');
                        $('.imageGallery img').unbind('click').click(function () {
                            $('label[for="blog_form_image"]').trigger('click');
                        });
                    }
                    $.each(value, function (i, v) {
                        ;
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