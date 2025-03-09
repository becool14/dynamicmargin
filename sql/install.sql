CREATE TABLE IF NOT EXISTS `PREFIX_dynamic_margin_history` (
    `id_margin_history` int(11) NOT NULL AUTO_INCREMENT,
    `margin_value` decimal(20,6) NOT NULL,
    `previous_value` decimal(20,6) NOT NULL,
    `date_add` datetime NOT NULL,
    `id_employee` int(11) NOT NULL,
    PRIMARY KEY (`id_margin_history`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `PREFIX_dynamic_margin_product` (
    `id_product` int(11) NOT NULL,
    `margin_value` decimal(20,6) NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY (`id_product`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;