SELECT
	nr,
	Px,
	Datum,
	Name,
	Wohnort,
	ArtDesAntrags,
	Klasse,
	Gebuehren
	FAER,
	ZFER_Probe,
	ZFER,
	(Gebuehren + FAER + ZFER_Probe + ZFER) AS Summe,
	Status,
	Beleg,
	Bemerkung,
	lastChanged,
	lastCHangedUser
FROM wp_ha_fuehrerscheinstelle_gebuehrenliste