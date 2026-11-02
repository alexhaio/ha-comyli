SELECT Name, Vorname, ort, strasse, nr, etage, lage, Zimmer, Geburtsdatum
FROM personen
LEFT JOIN unterkuenfte ON personen.unterkunftid=unterkuenfte.id
WHERE geloescht = 0