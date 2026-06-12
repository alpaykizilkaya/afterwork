-- Seeker "Şirket Sorularım": stored answers to a predefined question set.
-- Answers are reused to pre-fill future applications.
CREATE TABLE IF NOT EXISTS seeker_question_answers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id INT UNSIGNED NOT NULL,
  question_key VARCHAR(48) NOT NULL,
  answer TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_acc_q (account_id, question_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
