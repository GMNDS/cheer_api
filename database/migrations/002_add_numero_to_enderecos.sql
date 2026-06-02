ALTER TABLE `enderecos`
  ADD COLUMN `numero` varchar(20) NULL AFTER `codigo_postal`,
  ADD COLUMN `complemento` varchar(255) NULL AFTER `numero`;