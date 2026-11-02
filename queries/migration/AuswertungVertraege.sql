SELECT strasse, nr, etage, lage, vertraege.*
FROM `vertraege`
LEFT JOIN unterkuenfte ON vertraege.unterkunft = unterkuenfte.id