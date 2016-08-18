CREATE TABLE IF NOT EXISTS `boxio_scenarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `frame_number` int(4) NOT NULL,
  `id_legrand` int(11) NOT NULL,
  `unit` tinyint(4) NOT NULL,
  `id_legrand_listen` int(11) NOT NULL,
  `unit_listen` tinyint(4) NOT NULL,
  `value_listen` int(11) DEFAULT NULL,
  `media_listen` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `scenario` (`id_legrand`,`unit`,`id_legrand_listen`,`unit_listen`),
  KEY `ref_legrand` (`id_legrand`),
  KEY `ref_legrand_listen` (`id_legrand_listen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;