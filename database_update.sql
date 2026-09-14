-- ============================================================
-- CarPrice Predictor — optional catalog seed
-- Run this AFTER importing DATABASE_SQL.
-- Adds more brands/models to reference_weights so the Predict
-- page's Brand -> Model dropdowns have a realistic catalog to
-- work with. Safe to skip if you already have your own data.
-- ============================================================

-- Prevent the same vehicle_type + brand + model from being added twice in future
ALTER TABLE `reference_weights`
  ADD UNIQUE KEY `uniq_vehicle` (`vehicle_type`, `brand`, `model`);

INSERT INTO `reference_weights` (`vehicle_type`, `brand`, `model`, `base_price`, `yearly_depreciation`, `mileage_penalty`) VALUES
-- Cars
('Car', 'Toyota', 'Fortuner', 1850000.00, 85000.00, 0.6500),
('Car', 'Toyota', 'Innova', 1250000.00, 60000.00, 0.5800),
('Car', 'Toyota', 'Wigo', 620000.00, 30000.00, 0.4500),
('Car', 'Honda', 'City', 980000.00, 50000.00, 0.5500),
('Car', 'Honda', 'CR-V', 1650000.00, 78000.00, 0.6200),
('Car', 'Mitsubishi', 'Mirage', 680000.00, 32000.00, 0.4700),
('Car', 'Mitsubishi', 'Montero Sport', 1900000.00, 88000.00, 0.6600),
('Car', 'Ford', 'Ranger', 1550000.00, 72000.00, 0.6000),
('Car', 'Nissan', 'Almera', 850000.00, 42000.00, 0.5200),
('Car', 'Hyundai', 'Accent', 800000.00, 40000.00, 0.5100),
('Car', 'Kia', 'Picanto', 620000.00, 29000.00, 0.4400),
('Car', 'Suzuki', 'Ertiga', 900000.00, 44000.00, 0.5300),
('Car', 'Mazda', 'Mazda 3', 1300000.00, 62000.00, 0.5900),
('Car', 'Isuzu', 'D-Max', 1600000.00, 74000.00, 0.6100)
ON DUPLICATE KEY UPDATE base_price = VALUES(base_price);

INSERT INTO `reference_weights` (`vehicle_type`, `brand`, `model`, `base_price`, `yearly_depreciation`, `mileage_penalty`) VALUES
-- Motorcycles
('Motorcycle', 'Yamaha', 'Mio i125', 78000.00, 5800.00, 0.1400),
('Motorcycle', 'Honda', 'Beat', 75000.00, 5500.00, 0.1300),
('Motorcycle', 'Suzuki', 'Raider R150', 130000.00, 10500.00, 0.2200)
ON DUPLICATE KEY UPDATE base_price = VALUES(base_price);

INSERT INTO `reference_weights` (`vehicle_type`, `brand`, `model`, `base_price`, `yearly_depreciation`, `mileage_penalty`) VALUES
-- Bicycles
('Bicycle', 'Giant', 'Escape 3', 38000.00, 3200.00, 0.0400)
ON DUPLICATE KEY UPDATE base_price = VALUES(base_price);
