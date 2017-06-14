<script>
    function pageReady(f) {
        if (!window.Root) {
            setTimeout(function() {
                pageReady(f);
            },10)
        }
        else f();
    }

    window.yardurl = <?= json_encode($page->base->renderUrl()) ?>;
    window.pageName = '<?= ($page->placeholder ?  $page->placeholder->alias : $page->alias) ?>';
    pageReady(function() {
        window.render(window.pageName);
    });
</script>