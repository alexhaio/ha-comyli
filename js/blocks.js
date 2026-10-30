(function () {
    const { registerBlockType } = wp.blocks;
    const { createElement: el, Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor || wp.editor;
    const {
        PanelBody,
        SelectControl,
        ToggleControl,
        TextControl,
        RangeControl,
        ColorPalette,
    } = wp.components;
    const ServerSideRender = wp.serverSideRender || wp.components.ServerSideRender;

    const blockData = window.comyliBlockData || { queries: [], tables: [] };

    const queryOptions = [{ label: '— Keine Abfrage —', value: '' }].concat(
        (blockData.queries || []).map(q => ({ label: q, value: q }))
    );

    const tableOptions = [{ label: '— Keine Tabelle —', value: '' }].concat(
        (blockData.tables || []).map(t => ({ label: t, value: t }))
    );

    // =========================================================================
    // 1. Block: ha-comyli/query-table
    // =========================================================================
    registerBlockType('ha-comyli/query-table', {
        title: 'CoMyLi Tabelle',
        description: 'Bettet eine SQL-Abfrage oder Tabelle als interaktive Datenansicht ein.',
        icon: 'editor-table',
        category: 'widgets',
        attributes: {
            query: { type: 'string', default: '' },
            table: { type: 'string', default: '' },
            layout: { type: 'string', default: 'clean' },
            filter: { type: 'boolean', default: true },
            csvExport: { type: 'boolean', default: false },
            edit: { type: 'boolean', default: false },
            limit: { type: 'number', default: 25 },
            params: { type: 'string', default: '' },
        },
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const hasSource = Boolean(attributes.query || attributes.table);

            return el(
                Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: 'Datenquelle', initialOpen: true },
                        el(SelectControl, {
                            label: 'Abfrage (.sql Datei)',
                            value: attributes.query,
                            options: queryOptions,
                            onChange: val => setAttributes({ query: val, table: val ? '' : attributes.table }),
                            help: 'Wähle eine gespeicherte SQL-Abfrage aus.',
                        }),
                        el(SelectControl, {
                            label: 'Oder: Datenbanktabelle',
                            value: attributes.table,
                            options: tableOptions,
                            onChange: val => setAttributes({ table: val, query: val ? '' : attributes.query }),
                            help: 'Wähle direkt eine MySQL-Tabelle aus.',
                        }),
                        el(TextControl, {
                            label: 'Dynamische Parameter',
                            value: attributes.params,
                            placeholder: 'person_id=123&status=aktiv',
                            onChange: val => setAttributes({ params: val }),
                            help: 'Werte für :platzhalter in der SQL-Abfrage.',
                        })
                    ),
                    el(
                        PanelBody,
                        { title: 'Darstellung & Optionen', initialOpen: false },
                        el(SelectControl, {
                            label: 'Layout-Modus',
                            value: attributes.layout,
                            options: [
                                { label: 'Clean / Eingebettet (empfohlen)', value: 'clean' },
                                { label: 'Vollständiges Interface', value: 'default' },
                            ],
                            onChange: val => setAttributes({ layout: val }),
                        }),
                        el(RangeControl, {
                            label: 'Zeilen pro Seite',
                            value: attributes.limit,
                            onChange: val => setAttributes({ limit: val }),
                            min: 5,
                            max: 200,
                            step: 5,
                        }),
                        el(ToggleControl, {
                            label: 'Filterzeile anzeigen',
                            checked: attributes.filter,
                            onChange: val => setAttributes({ filter: val }),
                        }),
                        el(ToggleControl, {
                            label: 'CSV-Export erlauben',
                            checked: attributes.csvExport,
                            onChange: val => setAttributes({ csvExport: val }),
                        }),
                        el(ToggleControl, {
                            label: 'Bearbeitungsmodus (Inline-Edit)',
                            checked: attributes.edit,
                            onChange: val => setAttributes({ edit: val }),
                        })
                    )
                ),
                hasSource && ServerSideRender
                    ? el(ServerSideRender, {
                          block: 'ha-comyli/query-table',
                          attributes: attributes,
                      })
                    : el(
                          'div',
                          { className: 'comyli-block-placeholder' },
                          el('strong', null, '📊 CoMyLi Tabelle'),
                          el(
                              'p',
                              null,
                              'Bitte wähle in der rechten Seitenleiste eine Abfrage oder Tabelle aus.'
                          )
                      )
            );
        },
        save: function () {
            return null; // Dynamischer Block (Server-Side-Rendering)
        },
    });

    // =========================================================================
    // 2. Block: ha-comyli/chart
    // =========================================================================
    registerBlockType('ha-comyli/chart', {
        title: 'CoMyLi Diagramm',
        description: 'Visualisiert SQL-Abfragen als interaktives Chart.js Diagramm.',
        icon: 'chart-bar',
        category: 'widgets',
        attributes: {
            query: { type: 'string', default: '' },
            table: { type: 'string', default: '' },
            chartType: { type: 'string', default: 'bar' },
            labelColumn: { type: 'string', default: '' },
            valueColumn: { type: 'string', default: '' },
            title: { type: 'string', default: '' },
            height: { type: 'number', default: 350 },
        },
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const hasSource = Boolean(attributes.query || attributes.table);

            return el(
                Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: 'Diagramm-Einstellungen', initialOpen: true },
                        el(TextControl, {
                            label: 'Diagramm-Titel',
                            value: attributes.title,
                            placeholder: 'z. B. Auslastungsquote nach Standort',
                            onChange: val => setAttributes({ title: val }),
                        }),
                        el(SelectControl, {
                            label: 'Diagrammtyp',
                            value: attributes.chartType,
                            options: [
                                { label: 'Balkendiagramm (Bar)', value: 'bar' },
                                { label: 'Liniendiagramm (Line)', value: 'line' },
                                { label: 'Kreisdiagramm (Pie)', value: 'pie' },
                                { label: 'Ringdiagramm (Doughnut)', value: 'doughnut' },
                            ],
                            onChange: val => setAttributes({ chartType: val }),
                        }),
                        el(SelectControl, {
                            label: 'Datenquelle: Abfrage',
                            value: attributes.query,
                            options: queryOptions,
                            onChange: val => setAttributes({ query: val, table: val ? '' : attributes.table }),
                        }),
                        el(SelectControl, {
                            label: 'Oder: Tabelle',
                            value: attributes.table,
                            options: tableOptions,
                            onChange: val => setAttributes({ table: val, query: val ? '' : attributes.query }),
                        }),
                        el(TextControl, {
                            label: 'Beschriftungsspalte (X-Achse)',
                            value: attributes.labelColumn,
                            placeholder: 'Erste Spalte standardmäßig',
                            onChange: val => setAttributes({ labelColumn: val }),
                        }),
                        el(TextControl, {
                            label: 'Wertspalte (Y-Achse)',
                            value: attributes.valueColumn,
                            placeholder: 'Zweite Spalte standardmäßig',
                            onChange: val => setAttributes({ valueColumn: val }),
                        }),
                        el(RangeControl, {
                            label: 'Höhe (px)',
                            value: attributes.height,
                            onChange: val => setAttributes({ height: val }),
                            min: 200,
                            max: 700,
                            step: 25,
                        })
                    )
                ),
                hasSource && ServerSideRender
                    ? el(ServerSideRender, {
                          block: 'ha-comyli/chart',
                          attributes: attributes,
                      })
                    : el(
                          'div',
                          { className: 'comyli-block-placeholder' },
                          el('strong', null, '📈 CoMyLi Diagramm'),
                          el(
                              'p',
                              null,
                              'Bitte wähle in der Seitenleiste eine Abfrage mit Beschriftungs- und Wertspalten aus.'
                          )
                      )
            );
        },
        save: function () {
            return null;
        },
    });

    // =========================================================================
    // 3. Block: ha-comyli/kpi-card
    // =========================================================================
    registerBlockType('ha-comyli/kpi-card', {
        title: 'CoMyLi Kennzahl (KPI)',
        description: 'Zeigt eine zentrale Kennzahl mit Trendanzeige aus einer SQL-Abfrage an.',
        icon: 'dashboard',
        category: 'widgets',
        attributes: {
            query: { type: 'string', default: '' },
            table: { type: 'string', default: '' },
            title: { type: 'string', default: 'Gesamtanzahl' },
            unit: { type: 'string', default: '' },
            trend: { type: 'string', default: '' },
            trendDirection: { type: 'string', default: 'up' },
            icon: { type: 'string', default: '📊' },
            color: { type: 'string', default: '#3b82f6' },
        },
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const hasSource = Boolean(attributes.query || attributes.table);

            return el(
                Fragment,
                null,
                el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: 'KPI Kachel-Inhalt', initialOpen: true },
                        el(TextControl, {
                            label: 'Titel',
                            value: attributes.title,
                            onChange: val => setAttributes({ title: val }),
                        }),
                        el(TextControl, {
                            label: 'Symbol / Icon (Emoji)',
                            value: attributes.icon,
                            onChange: val => setAttributes({ icon: val }),
                        }),
                        el(TextControl, {
                            label: 'Einheit (Suffix)',
                            value: attributes.unit,
                            placeholder: 'z. B. Betten, %, €',
                            onChange: val => setAttributes({ unit: val }),
                        }),
                        el(TextControl, {
                            label: 'Trend-Text',
                            value: attributes.trend,
                            placeholder: 'z. B. +12% ggü. Vormonat',
                            onChange: val => setAttributes({ trend: val }),
                        }),
                        el(SelectControl, {
                            label: 'Trend-Richtung',
                            value: attributes.trendDirection,
                            options: [
                                { label: 'Positiv (Grün)', value: 'up' },
                                { label: 'Negativ (Rot)', value: 'down' },
                                { label: 'Neutral (Grau)', value: 'neutral' },
                            ],
                            onChange: val => setAttributes({ trendDirection: val }),
                        })
                    ),
                    el(
                        PanelBody,
                        { title: 'Datenquelle', initialOpen: false },
                        el(SelectControl, {
                            label: 'Abfrage (.sql Datei)',
                            value: attributes.query,
                            options: queryOptions,
                            onChange: val => setAttributes({ query: val, table: val ? '' : attributes.table }),
                            help: 'Abfrage, die als erstes Feld den Zählwert liefert (z. B. SELECT COUNT(*)).',
                        }),
                        el(SelectControl, {
                            label: 'Oder: Tabelle (Gesamtzeilen)',
                            value: attributes.table,
                            options: tableOptions,
                            onChange: val => setAttributes({ table: val, query: val ? '' : attributes.query }),
                        })
                    )
                ),
                hasSource && ServerSideRender
                    ? el(ServerSideRender, {
                          block: 'ha-comyli/kpi-card',
                          attributes: attributes,
                      })
                    : el(
                          'div',
                          { className: 'comyli-block-placeholder' },
                          el('strong', null, '🎯 CoMyLi KPI Kachel'),
                          el('p', null, 'Bitte wähle eine Abfrage oder Tabelle für die Kennzahl aus.')
                      )
            );
        },
        save: function () {
            return null;
        },
    });
})();
