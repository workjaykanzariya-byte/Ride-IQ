CREATE TABLE membership_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zoho_plan_code VARCHAR(120) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  interval_count INT UNSIGNED NOT NULL DEFAULT 1,
  interval_unit VARCHAR(50) NOT NULL,
  currency_code CHAR(3) NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'active',
  raw_response_json JSON NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX idx_membership_plans_status (status)
);
CREATE TABLE user_subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  membership_plan_id BIGINT UNSIGNED NOT NULL,
  zoho_subscription_id VARCHAR(120) NULL UNIQUE,
  zoho_customer_id VARCHAR(120) NULL,
  zoho_hostedpage_id VARCHAR(120) NULL,
  payment_reference VARCHAR(120) NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'pending',
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency_code CHAR(3) NOT NULL,
  start_date DATETIME NULL,
  end_date DATETIME NULL,
  next_billing_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  raw_response_json JSON NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX idx_sub_user_status (user_id, status),
  INDEX idx_sub_hostedpage (zoho_hostedpage_id),
  CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sub_plan FOREIGN KEY (membership_plan_id) REFERENCES membership_plans(id)
);
CREATE TABLE payment_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  subscription_id BIGINT UNSIGNED NOT NULL,
  transaction_reference VARCHAR(120) NOT NULL UNIQUE,
  payment_gateway VARCHAR(50) NOT NULL DEFAULT 'zoho',
  payment_status VARCHAR(50) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency_code CHAR(3) NOT NULL,
  payment_method VARCHAR(50) NULL,
  paid_at DATETIME NULL,
  raw_response_json JSON NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX idx_payment_status (payment_status),
  CONSTRAINT fk_payment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_subscription FOREIGN KEY (subscription_id) REFERENCES user_subscriptions(id) ON DELETE CASCADE
);
CREATE TABLE zoho_webhook_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(120) NOT NULL,
  webhook_data JSON NOT NULL,
  status VARCHAR(50) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_webhook_event_created (event_type, created_at)
);
