<script>
    function pageReady(f) {
        if (!window.Root) {
            setTimeout(function() {
                pageReady(f);
            },10)
        }
        else f();
    }

    window.yard = {
        url: <?= json_encode($page->base->renderUrl()) ?>,
        page: {
            name: '<?= ($page->placeholder ?  $page->placeholder->alias : $page->alias) ?>'
        },
        offline: <?= json_encode($page->base->offline); ?>
    };
    
    pageReady(function() {
        window.render(window.yard.page.name);
    });
</script>