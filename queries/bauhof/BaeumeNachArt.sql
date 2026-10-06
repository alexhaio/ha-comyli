SELECT ArtLat, ArtDeu, COUNT(`BaumNr`) AS AnzahlBaeume
FROM `arbokat`
LEFT JOIN `tbl_baumarten` ON `ArtLateinisch` = ArtLat
WHERE gefaellt IS NULL
GROUP BY `ArtLat`