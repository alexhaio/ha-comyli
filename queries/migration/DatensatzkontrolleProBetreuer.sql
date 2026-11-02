SELECT
	unterkuenfte.betreuerIn,
    SUM(IF(
			(
				personen.basisdatenaenderung < DATE_SUB(NOW(), INTERVAL 90 DAY)
				AND personen.adminpruefung < DATE_SUB(NOW(), INTERVAL 90 DAY)
			)
			OR personen.lastChanged < DATE_SUB(NOW(), INTERVAL 90 DAY),
        1,0
    )) AS alteDaten
FROM personen
LEFT JOIN unterkuenfte ON unterkuenfte.id=personen.unterkunftid
WHERE (
	personen.basisdatenaenderung < DATE_SUB(NOW(), INTERVAL 90 DAY)
	OR personen.lastChanged < DATE_SUB(NOW(), INTERVAL 90 DAY)
)
	AND geloescht = 0
GROUP BY betreuerIn