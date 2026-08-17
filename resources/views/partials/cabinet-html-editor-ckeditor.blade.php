<script src="{{ asset('/plugins/ckeditor/ckeditor.js') }}" charset="utf-8"></script>
<script src="{{ asset('/plugins/ckeditor/adapters/jquery.js') }}" charset="utf-8"></script>
<script>
    // Иначе CKEditor вешает плавающий тулбар на любой contenteditable (блок подсветки Есенина и т.п.).
    if (window.CKEDITOR) {
        window.CKEDITOR.disableAutoInline = true;
    }
</script>
