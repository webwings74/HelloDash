-- HelloDash: schema voor het Vestaboard-achtige berichtenbord.
--
-- Eén tabel, append-only: de rij met het hoogste id is het huidige (enige)
-- bericht dat op het dashboard wordt getoond. Geen wachtrij, geen
-- 'active'-vlag.

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `grid_json` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Elke rij is één volledig bordbericht (6x24 raster als JSON). De rij met het hoogste id is het huidige bericht.';

-- Voorbeeldbericht ("HELLODASH", gecentreerd), zodat het dashboard na het
-- importeren niet leeg start. Wordt alleen ingevoegd als de tabel nog leeg
-- is, dus opnieuw importeren is veilig en zet geen tweede rij neer.
INSERT INTO `messages` (`grid_json`)
SELECT '[["","","","","","","","","","","","","","","","","","","","","","","",""],["","","","","","","","","","","","","","","","","","","","","","","",""],["","","","","","","","H","E","L","L","O","D","A","S","H","","","","","","","",""],["","","","","","","","","","","","","","","","","","","","","","","",""],["","","","","","","","","","","","","","","","","","","","","","","",""],["","","","","","","","","","","","","","","","","","","","","","","",""]]'
WHERE NOT EXISTS (SELECT 1 FROM `messages`);

-- Bordinstellingen (sleutel/waarde): 'font' (lettertype van de tegels) en
-- 'theme' (dark/light/auto), gekozen in bericht.php. Ontbreekt een rij (of
-- de hele tabel), dan valt de site terug op Fjalla One en donker. De site
-- maakt deze tabel ook zelf aan. Opnieuw importeren is veilig.
CREATE TABLE IF NOT EXISTS `settings` (
  `name` varchar(64) NOT NULL,
  `value` varchar(255) NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Bordinstellingen als sleutel/waarde, bijv. font en theme.';
