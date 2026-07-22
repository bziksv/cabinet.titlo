{{-- Поиск отчёта по названию + пресеты приоритета. Ожидает родителя .cabinet-sa-tree[data-sa-tree]. --}}
<div class="cabinet-sa-tree-controls">
    <input type="search"
           class="form-control form-control-sm cabinet-sa-tree-search"
           placeholder="Поиск отчёта…"
           autocomplete="off"
           aria-label="Поиск отчёта по названию">
    <div class="cabinet-sa-tree-presets" role="group" aria-label="Пресеты отчётов">
        <button type="button" class="cabinet-sa-tree-preset is-active" data-preset="all">Все</button>
        <button type="button" class="cabinet-sa-tree-preset" data-preset="hot">С находками</button>
        <button type="button" class="cabinet-sa-tree-preset" data-preset="critical">Грубые</button>
        <button type="button" class="cabinet-sa-tree-preset" data-preset="other">Прочие</button>
        <button type="button" class="cabinet-sa-tree-preset" data-preset="warning">Замечания</button>
        <button type="button" class="cabinet-sa-tree-preset" data-preset="info">Инфо</button>
    </div>
</div>
