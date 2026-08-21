# Таблица ключевых слов мониторинга: FixedColumns (эталон fc53)

Страница: `/monitoring/{id}#keywords` (пример: `/monitoring/63#keywords`).

**Это проверенный production-ready эталон.** Не переписывать на CSS `position: sticky` и не «упрощать» без чтения этого документа — иначе снова получите наложение колонок, щель между «Запрос» и «URL», гигантские строки, пропавший заголовок «Запрос», сдвиг шапки при горизонтальном скролле и отставание при вертикальном скролле.

**Baseline tag: `fc53`** — 2026-08-21 (unveil после FC). Prior: `fc50`/`fc47`. Cache-bust: `-fc53`.
---

## Симптомы, если сломать

| Симптом | Типичная причина |
|--------|-------------------|
| URL/группы наезжают на «Запрос» при горизонтальном скролле | Вернули CSS sticky вместо FixedColumns |
| Щель 50–200px между FC-блоком и первой видимой колонкой | Скрытые колонки в scroll-body занимают ширину; нет `applyScrollTableFcInset`; зум 80% без `visualViewport` relayout |
| Строки 200–280px высотой | Скрытые ячейки `width:0` **без** `display:none` на дочерних элементах — текст переносится вертикально |
| Строки «разъезжаются» по вертикали при hover/скролле | Не вызывается `syncMonTableRowHeights` или FC `heightMatch` конфликтует с inline height |
| Левая часть «догоняет» правую при быстром скролле | Нет rAF-sync; не отключён `scroll.DTFC` у плагина; wheel без немедленного sync |
| Заголовок «Запрос» пропал / шапка FC сдвинута | `margin-left` на FC head table = inset scroll-body (~488px) — нужен `resetFcCloneTableMargin` |
| FC colgroup `[0px, 0px, 0px]` после load/sort/page 2 | `buildFcCloneColgroup` применил ширины **всех** колонок scroll-таблицы вместо только left N; или вызван `api.columns.adjust()` |
| Layout «плывёт» через ~1 с после reload | `api.columns.adjust()` / `tryRevealMonitoringTable` ломает FC colgroup |
| Нет серой полоски hover на строке | CSS `#fff !important` на ячейках; нужен `paintMonTableRowHover` с inline `background-color` |
| Шапка «уезжает» при докрутке вправо | `scrollHeadInner` шире viewport тела; нет `fitMonTableScrollHeadInner` |
| Шапка и тело разъезжаются при 2–3 датах / выключенных столбцах | Таблица растянута на 100% viewport; нет `lockMonScrollTablesWidth` |
| macOS: шапка короче тела, сдвиг в конце | **Не чинить** fallback `barGap=15` и `scrollbar-gutter: stable` — fc48 ломало выравнивание (`lastDelta: -15`). Эталон: только `offsetWidth - clientWidth` |

---

## Архитектура (кратко)

```
┌─────────────────────────────────────────────────────────────┐
│  DTFC_LeftWrapper (FC-клон: чекбокс, кнопки, «Запрос»)      │
│  z-index 12, фиксированная ширина ~488px (46+62+380)        │
├─────────────────────────────────────────────────────────────┤
│  .dataTables_scrollHeadInner  ← width = clientWidth тела     │
│  .dataTables_scrollBody       ← scrollX + scrollY            │
│    table margin-left = ширина FC ( --mon-scroll-edge-nudge )  │
│    скрытые col 0..2: width 0, children display:none         │
│    edge col (первая видимая, URL): cabinet-mon-scroll-edge  │
└─────────────────────────────────────────────────────────────┘
         ↑
  #cabinetMonScrollNav — кнопки ↑↓ прокрутки страницы (fixed bottom-right)
```

**Два слоя DOM:** FixedColumns рисует левый блок; основная таблица в scrollX содержит те же колонки, но первые N скрыты (placeholder для colgroup), контент сдвинут `margin-left`.

---

## Файлы (не трогать разрозненно)

| Файл | Роль |
|------|------|
| `resources/views/monitoring/show.blade.php` | DataTables init: `scrollX`, `fixedColumns: { leftColumns, heightMatch: 'auto' }`, assets FC, `#cabinetMonScrollNav`, `finalizeMonTableLayout` в `initComplete` / `drawCallback` / unveil |
| `public/js/cabinet-monitoring-show-chrome.js` | Вся логика layout, inset, row heights, scroll sync, hover, scroll nav |
| `public/css/cabinet-monitoring-show.css` | Блок `.DTFC_*`, hidden columns, edge col, hover `is-row-hover`, `scrollHeadInner`, `.cabinet-mon-scroll-nav` |
| `public/plugins/datatables-fixedcolumns/` | Плагин FixedColumns (не удалять) |
| `.cursor/rules/monitoring-keywords-table-fc.mdc` | Cursor rule — читать перед правками |

Cache-bust: суффикс `-fc47` на CSS/JS в `show.blade.php` (при правках увеличивать: `-fc48`…).

---

## Порядок layout (обязательный)

Вызывается из `finalizeMonTableLayout()`:

1. `destroyFixedColumns` (если `rebuildFixedColumns`) → `ensureFixedColumns` → `fixedColumns().relayout()` → **`restoreFcLeftColgroup`**
2. `enforceMonColumnWidths` — scroll colgroup: left cols = `0px`, остальные = номинал
3. `syncFixedLeftBlock(api)` — ширины FC, hidden/edge, inset, row heights, **`resetFcCloneTableMargin`**, **`fitMonTableScrollHeadInner`**
4. `wireMonTableRowHover` — класс `is-row-hover` + inline paint на обеих половинах
5. `repairMonTableRenderedRows` — если tbody пустой при наличии данных
6. Двойной `requestAnimationFrame`: `syncMonTableRowHeights` + `remeasureMainTableLeftHiddenWidths` + `relayoutMonTableFcLayout` + повторный `wireMonTableRowHover`

### `syncFixedLeftBlock` внутри

1. Colgroup/ширины FC-клона через **`buildFcCloneColgroup`** (только left N колонок, обычно `[46px, 62px, 380px]`)
2. `hideMainTableLeftColumns` — классы `cabinet-mon-scrollhead-left-hidden`, `cabinet-mon-scrollbody-left-hidden`, `cabinet-mon-scroll-edge-col`
3. `syncMainTableLeftHiddenWidths` — colgroup с `0px` для скрытых; inline zero width
4. **`restoreFcLeftColgroup`** — перезаписывает FC colgroup после любых width-операций
5. `applyScrollTableFcInset` — `margin-left` scroll-таблиц = ширина FC ± gap
6. `clearMonTableRowInlineHeights` → `syncMonTableRowHeights`
7. `syncMonTableScrollPositions` + `muteFcVerticalScrollHandlers`
8. **`resetFcCloneTableMargin`** — сбрасывает `margin-left: 0` на FC head/body tables (иначе «Запрос» уезжает)
9. **`fitMonTableScrollHeadInner`** — constrains head inner to body viewport width

### `buildFcCloneColgroup` / `restoreFcLeftColgroup`

- **Критично:** colgroup FC-клона = только первые `leftCount` колонок (checkbox, buttons, query), **не** все visible columns scroll-таблицы
- Вызывать `restoreFcLeftColgroup` после `enforceMonColumnWidths`, FC `relayout()`, `syncFixedLeftBlock`
- Задаёт `--mon-fc-left-width` на root

### `resetFcCloneTableMargin`

- FC head/body `<table>`: `marginLeft: 0`, `marginRight: 0`
- Без этого DataTables/FC может проставить `margin-left` = inset scroll-body → заголовок «Запрос» невидим

### `fitMonTableScrollHeadInner` (fc47)

```javascript
var barGap = Math.max(0, bodyEl.offsetWidth - bodyEl.clientWidth);
$headInner.css({
    width: bodyEl.clientWidth + 'px',
    maxWidth: bodyEl.clientWidth + 'px',
    paddingRight: barGap > 0 ? barGap + 'px' : '0',
    boxSizing: 'content-box',
    overflow: 'hidden',
});
```

- Шапка scrollX = viewport тела, не полная ширина таблицы (~2796px)
- Иначе `scrollLeft` на headInner не работает — заголовки «уезжают» вправо
- **Не добавлять** macOS fallback `barGap = 15` (fc48) — overcorrect
- **Не добавлять** `scrollbar-gutter: stable` на scrollBody (fc48)

CSS дублирует: `.dataTables_scrollHeadInner { box-sizing: content-box; overflow: hidden; }`

### `applyScrollTableFcInset`

- Измеряет gap: `edgeCol.left - fcWrapper.right`
- `margin-left` на `.dataTables_scrollHeadInner table` и `.dataTables_scrollBody table`
- CSS fallback: `margin-left: var(--mon-scroll-edge-nudge, 0)`
- **Положительный** inset (~488px), не отрицательный «nudge»

### `syncMainTableLeftHiddenWidths`

- Скрытые колонки в **scroll-body**: ширина **0**
- CSS **обязательно** скрывает содержимое ячеек:

```css
.cabinet-mon-scrollbody-left-hidden > * { display: none !important; }
```

Без этого — гигантские строки.

### `syncMonTableRowHeights`

- Попарно main row ↔ FC row, `height = max(…)`, двухпроходная подстройка
- Если высота > 120px — берётся только FC (защита от битого main)

---

## Вертикальный скролл

Плагин FC вешает `scroll.DTFC` и дублирует `scrollTop` → микролаг.

**Наш sync (`wireMonTableScrollSync`):**

1. `muteFcVerticalScrollHandlers` — `off('scroll.DTFC')` на main scroller и left liner (после каждого FC relayout!)
2. Capture `scroll` → `kickMonTableScrollSync` → rAF-цикл (480ms tail)
3. Bubble `scroll` → sync + rAF sync
4. `wheel` (passive: **false**) на scroll-body: `scrollTop += deltaY` + sync в том же тике
5. `touchmove`, `scrollend` — финальная подтяжка
6. `force` sync: всегда пишет `leftLiner.scrollTop = st` + `void leftLiner.scrollHeight`
7. **`fitMonTableScrollHeadInner`** вызывается в начале `syncMonTableScrollPositions`

**Не использовать:** `transform` на `<table>` / `<tbody>` для sync — в Chrome не двигает строки; ломает выравнивание.

---

## Hover строк (fc45+)

- Bootstrap `table-hover` на FC **отключён** в CSS
- Класс `is-row-hover` на **обеих** половинах по **index** (`wireMonTableRowHover`)
- **`paintMonTableRowHover`** — inline `background-color: #e9ecef !important` на все `td` (CSS `#fff !important` на pos-hit/near иначе побеждает)
- `clearMonTableRowHover` при draw / перед новым hover
- Переподключать в `onTableReady`, `drawCallback`, `finalizeMonTableLayout`, double rAF

---

## Scroll nav (fc46+)

Когда колёсико мыши «застряло» в зоне таблицы — прокрутка страницы блокируется.

- HTML: `#cabinetMonScrollNav` в `show.blade.php` (кнопки ↑↓)
- CSS: `.cabinet-mon-scroll-nav` fixed bottom-right; скрыт на `data-view='overview'`
- JS: `wireCabinetMonScrollNav()` — scroll page на ~72% viewport, `updateMonScrollNavState` disable at edges
- Авто-init при загрузке chrome.js

---

## Пустая таблица / pagination (fc41+)

- `repairMonTableRenderedRows` — если `aoData` есть, а tbody пустой: `clearMonTableDetachedRowNodes` + `draw(false)`
- `ensureMonTableAjaxReady` — восстанавливает `bAjaxDataGet` после repair
- **Не вызывать** `api.columns.adjust()` в `tryRevealMonitoringTable`

---

## Зум браузера (80% и т.д.)

`resize` **не всегда** срабатывает при zoom. Есть:

- `visualViewport` `resize` / `scroll` → `scheduleMonTableViewportRelayout` → `relayoutMonTableFcLayout`

Без этого на 80% появляется щель между «Запрос» и «URL».

---

## Что НЕ делать (чёрный список)

1. ❌ CSS `position: sticky` для левых колонок внутри `scrollX`
2. ❌ Скрытые колонки `width:0` без `display:none` на `> *`
3. ❌ Отрицательный `margin-left` как единственный способ закрыть щель
4. ❌ `transform: translateY` на `<table>` для scroll sync
5. ❌ Оставлять `scroll.DTFC` плагина + свой sync без `muteFcVerticalScrollHandlers`
6. ❌ Задавать ширину скрытых колонок = номинал (46+62+380) в scroll-body — gap ~84px vs FC 488px
7. ❌ Менять только CSS или только JS — layout всегда парой
8. ❌ `enforceMonColumnWidths` без skip left cols при `_oFixedColumns`
9. ❌ **`api.columns.adjust()`** — обнуляет FC colgroup, layout «плывёт» через ~1 с. Вместо: `restoreFcLeftColgroup` + `syncFixedLeftBlock`
10. ❌ **`buildFcCloneColgroup` с ширинами всех visible columns** — FC colgroup станет `0px`
11. ❌ **fc48 macOS hacks**: `barGap = 15`, `scrollbar-gutter: stable`, двойной retry scrollLeft — overcorrect, `lastDelta: -15`
12. ❌ Полагаться только на CSS `is-row-hover` без `paintMonTableRowHover` — зелёные ячейки pos-hit остаются белыми

---

## Чеклист при баге

1. Hard refresh (Cmd+Shift+R), версия assets `…-fc47`
2. DevTools: gap `edgeCol.left - fcWrapper.right` ≈ 0; row height ≈ 42px
3. FC colgroup ≈ `[46px, 62px, 380px]`; FC right ≈ URL left ≈ 488–599px
4. `--mon-scroll-edge-nudge` / scroll `table.style.marginLeft` ≈ ширине FC (~488px)
5. FC head/body table `marginLeft` = `0` (не 488px)
6. `scrollHeadInner` width ≈ `scrollBody.clientWidth` (не ~2796px)
7. Докрутка вправо: `lastDelta` заголовок vs колонка ≈ 0
8. Hover: серая полоска `#e9ecef` на всей строке включая pos-hit
9. Sort / page 2 / 50→100 rows — layout стабилен через 5+ сек
10. Scroll nav ↑↓ работает на `#keywords`
11. После zoom 80%: relayout — gap ≈ 0
12. `leftLiner.scrollTop === scrollBody.scrollTop` при скролле

---

## Как повторить для другой таблицы

1. DataTables + `scrollX` + FixedColumns `leftColumns: N`, `heightMatch: 'auto'`
2. Скопировать паттерн из `cabinet-monitoring-show-chrome.js`:
   - `ensureFixedColumns`, `buildFcCloneColgroup`, `restoreFcLeftColgroup`, `resetFcCloneTableMargin`
   - `syncFixedLeftBlock`, `applyScrollTableFcInset`, `fitMonTableScrollHeadInner`
   - `wireMonTableScrollSync`, `wireMonTableRowHover`, `paintMonTableRowHover`
   - `finalizeMonTableLayout`, `repairMonTableRenderedRows`
3. Скопировать CSS: FixedColumns, hidden columns, scrollHeadInner, hover, scroll-nav
4. Маркировать hidden/edge колонки классами в JS
5. После FC `relayout()` — снова `muteFcVerticalScrollHandlers` + `restoreFcLeftColgroup`
6. Проверить: 100% zoom, 80% zoom, fast scroll, horizontal scroll to end, hover, sort, page 2, length change

---

## История итераций

| Tag | Что добавлено / исправлено |
|-----|--------------------------|
| fc21 | width 0 + display:none + positive inset + mute FC scroll + rAF/wheel sync + visualViewport |
| fc41–43 | colgroup fix, убран columns.adjust, repair empty tbody |
| fc44 | `resetFcCloneTableMargin` — заголовок «Запрос» |
| fc45 | `paintMonTableRowHover` inline + index-based hover |
| fc46 | `#cabinetMonScrollNav` scroll page buttons |
| fc47 | **`fitMonTableScrollHeadInner`** — horizontal scroll header sync; **эталон** |
| fc48 | ❌ macOS barGap=15, scrollbar-gutter — **откат**, ломало выравнивание |
| fc50 | Мало дат + скрытые столбцы: `lockMonScrollTablesWidth` (не 100% viewport), gap-correct inset, left-hidden vs col min-width |
| fc51 | Unveil после FC: не снимать `is-table-booting` до `onComplete` (иначе пустота вместо «Запрос») |
| fc52 | Gap ~488px при toggle столбцов: порог коррекции >480 мешал; zero left-hidden до замера; `--mon-fc-left-width` ≠ nudge |
| fc53 | Toggle столбцов: убран `rebuildFixedColumns` (дыра 0.5–1с); мгновенный `enforceMonColumnWidths` до debounce |

**Baseline tag: `fc51`** — production-ready для monitoring keywords (2026-08-21).
**Prior baseline: `fc50` / `fc47`.