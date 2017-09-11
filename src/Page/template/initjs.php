<script>
    function pageReady(f) {
        if (!window.Root) {
            setTimeout(function() {
                pageReady(f);
            },10)
        }
        else f();
    }

    window.plansys = {
        ui: {},
        url: <?= json_encode($page->base->renderUrl()) ?>,
        page: {
            name: '<?= ($page->placeholder ?  $page->placeholder->alias : $page->alias) ?>'
        },
        offline: <?= json_encode($page->base->offline); ?>
    };
    
    pageReady(function() {
        window.render(window.plansys.page.name);
    });
</script>