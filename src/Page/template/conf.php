{
    alias: '<?= $page->alias ?>',
    css: <?= $css ?>,
<?php if ($includeJS != ""): ?>
    includeJS: <?= $includeJS . "\n\t\t"; ?>,
<?php endif; ?>
<?php if ($page->placeholder): ?>
    placeholder: <?=  $placeholder; ?>,
<?php endif; ?>
    loaders: <?= $loaders . ",\n"; ?>
<?php if ($page->isRoot || $page->showDeps): ?>
    dependencies: <?= "\n\t\t" . $deps . "\n\t"; ?>,
<?php endif; ?>
<?php 
    $map = [];
    
    if ($mapStore) {
        $map[] = "\t\tinput: function() {\n\t\t\t   " . trim($mapStore) . "\n\t\t}";
    }
    
    if ($mapAction) {
        $map[] = "\t\taction: function() {\n\t\t\t   " . trim($mapAction) . "\n\t\t}";
    }
    
    if (count($map) > 0) {
        echo "\tmap: {\n";
        echo implode(",\n", $map);
        echo "\n\t},\n";
    } 
?>
<?php
    if ($page->isRoot) {
        $redux = [];
        
        if ($actionCreators) {
            $redux[] = "\t\tactionCreators: function() {\n\t\t\t   " . trim($actionCreators) . "\n\t\t}";
        }
        
        if ($reducers) {
            $redux[] = "\t\treducers: function(Immutable) {\n\t\t\t  " . trim($reducers) . "\n\t\t}";
        }

        if ($sagas) {
            $redux[] = "\t\tsagas: function() {
            // var take = e.take; var takem = e.takem; var put = e.put; var all = e.all; var race = e.race; var call = e.call; var apply = e.apply; var cps = e.cps; var fork = e.fork; var spawn = e.spawn; var join = e.join; var cancel = e.cancel; var select = e.select; var actionChannel = e.actionChannel; var cancelled = e.cancelled; var flush = e.flush; var getContext = e.getContext; var setContext = e.setContext; var takeEvery = e.takeEvery; var takeLatest = e.takeLatest; var throttle = e.throttle;
 
                " . trim($sagas) . "\n\t\t}";
        }

        if (count($redux) > 0) {
            echo "\tredux: {\n";
            echo implode(",\n", $redux);
            echo "\n\t},\n";
        } 
    }
?>
    js: function(Page) {
        <?= $js ?>
    },
    render: function(h) {
        return <?= implode("\n\t\t", explode("\n", $contents)); ?>;
    }
}

<?php if ($page->isRoot): ?>//# sourceURL=<?= $page->alias ?><?php endif; ?>