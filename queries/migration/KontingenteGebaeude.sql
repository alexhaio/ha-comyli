SELECT unterkuenfte.id, unterkuenfte.ort, strasse, nr, etage, lage, SUM(Zimmer.MaxBelegung) AS MaxBelegung, unterkuenfte.typ , betreuerIn
FROM unterkuenfte
LEFT JOIN Zimmer ON unterkuenfte.id=Zimmer.unterkunft
LEFT JOIN orte ON unterkuenfte.ort = orte.Name
WHERE statistischIrrelevant = 0 AND MaxBelegung IS NOT NULL
GROUP BY id