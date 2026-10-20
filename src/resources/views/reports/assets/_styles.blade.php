<style>
    @page { margin: 14mm; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; font-size: 9.5px; color: #222; }

    .header { border-bottom: 2px solid #1a56db; padding-bottom: 6px; margin-bottom: 10px; display: table; width: 100%; }
    .header .left, .header .right { display: table-cell; vertical-align: middle; }
    .header .right { text-align: right; }
    .company { font-size: 12px; font-weight: bold; color: #1a56db; }
    .doc-title { font-size: 15px; font-weight: bold; margin-top: 2px; }
    .doc-sub { font-size: 9px; color: #666; text-transform: uppercase; letter-spacing: 1px; }

    .section-title {
        font-size: 10px; font-weight: bold; color: #1a56db; text-transform: uppercase; letter-spacing: .5px;
        margin: 12px 0 4px; padding-bottom: 2px; border-bottom: 1px solid #e5e7eb;
    }

    table.grid { width: 100%; border-collapse: collapse; }
    table.grid td { padding: 3px 6px; border: 1px solid #e5e7eb; vertical-align: top; }
    table.grid td.label { background: #f9fafb; font-weight: bold; color: #444; width: 18%; }

    table.lines { width: 100%; border-collapse: collapse; }
    table.lines th { background: #1a56db; color: #fff; padding: 4px 5px; text-align: left; font-size: 8.5px; }
    table.lines td { padding: 3px 5px; border-bottom: 1px solid #e5e7eb; font-size: 8.5px; vertical-align: top; }
    table.lines tr:nth-child(even) td { background: #f9fafb; }
    .empty { color: #999; text-align: center; font-style: italic; }

    .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8.5px; font-weight: bold; }
    .badge-ok { background: #d1fae5; color: #065f46; }
    .badge-warn { background: #fef3c7; color: #92400e; }
    .badge-bad { background: #fee2e2; color: #991b1b; }

    .signatures { display: table; width: 100%; margin-top: 14px; }
    .sig-box { display: table-cell; width: 50%; padding: 0 12px; text-align: center; vertical-align: top; }
    .sig-label { font-weight: bold; font-size: 8.5px; color: #555; text-transform: uppercase; margin-bottom: 4px; }
    .sig-img { max-width: 170px; max-height: 60px; }
    .sig-name { margin-top: 4px; font-size: 8.5px; border-top: 1px solid #aaa; padding-top: 3px; }
    .sig-method { color: #2b6cb0; font-size: 7.5px; font-style: italic; }
    .sig-pending { color: #92400e; font-style: italic; margin-top: 24px; }

    .footer { margin-top: 14px; text-align: center; font-size: 7.5px; color: #999; border-top: 1px solid #e5e7eb; padding-top: 4px; }
</style>
