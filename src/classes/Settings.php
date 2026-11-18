<?php

/**
 * The settings of all forms (Project Wizard › Forms › Settings), stored in
 * content/.projectwizard/formwizard.json: retention, captcha, limit. The
 * captcha's secret key only encrypted – its key lives with the code
 * (site/config/formwizard.key, made on the first save), as the mail
 * server's password does.
 */
class fwSettings
{
	public const DEFAULTS = [
		'retention'      => 180,
		'captcha'        => 'none',   // none, friendly, turnstile, hcaptcha
		'captchaSiteKey' => '',
		'rate'           => true,
		'rateLimit'      => 5,
		'rateMinutes'    => 10,
	];

	public const CAPTCHAS = ['none', 'friendly', 'turnstile', 'hcaptcha'];

	public static function file(): string
	{
		return pwConfig::projectDir() . '/formwizard.json';
	}

	private static function stored(): array
	{
		$file = self::file();
		$data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
		return is_array($data) ? $data : [];
	}

	/** The settings (the retention from the option until set here) */
	public static function get(): array
	{
		$stored   = self::stored();
		$settings = array_merge(self::DEFAULTS, ['retention' => (int) option('kirbydesk.kirby-formwizard.retention', 180)], array_intersect_key($stored, self::DEFAULTS));
		$settings['retention']   = max(0, (int) $settings['retention']);
		$settings['captcha']     = in_array($settings['captcha'], self::CAPTCHAS, true) ? $settings['captcha'] : 'none';
		$settings['rate']        = (bool) $settings['rate'];
		$settings['rateLimit']   = max(1, (int) $settings['rateLimit']);
		$settings['rateMinutes'] = max(1, (int) $settings['rateMinutes']);
		return $settings;
	}

	/** For the Panel: never the secret, only whether it's there and readable */
	public static function forPanel(): array
	{
		$settings = self::get();
		$has = !empty(self::stored()['captchaSecret']);
		$settings['captchaSecret'] = '';
		$settings['captchaSecretState'] = $has ? (self::secret() !== null ? 'stored' : 'unreadable') : 'none';
		return $settings;
	}

	public static function save(array $changes): array
	{
		$stored = self::stored();
		foreach (self::DEFAULTS as $key => $default) {
			if (!array_key_exists($key, $changes)) continue;
			$value = $changes[$key];
			$stored[$key] = match (true) {
				is_bool($default) => in_array($value, [true, 'true', 1, '1'], true),
				is_int($default)  => (int) $value,
				default           => trim((string) $value),
			};
		}
		$stored['retention'] = max(0, min(3650, (int) ($stored['retention'] ?? 180)));
		if (!in_array($stored['captcha'] ?? 'none', self::CAPTCHAS, true)) $stored['captcha'] = 'none';
		if (isset($changes['captchaSecret']) && trim((string) $changes['captchaSecret']) !== '') {
			$stored['captchaSecret'] = self::encrypt(trim((string) $changes['captchaSecret']));
		}
		if (!empty($changes['removeCaptchaSecret'])) unset($stored['captchaSecret']);
		$file = self::file();
		if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);
		file_put_contents($file, json_encode($stored, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		return self::forPanel();
	}

	/* -------------- Steps done once (after an update) --------------*/

	public static function done(string $step): bool
	{
		return !empty(self::stored()['done'][$step]);
	}

	public static function markDone(string $step): void
	{
		$stored = self::stored();
		$stored['done'][$step] = true;
		$file = self::file();
		if (!is_dir(dirname($file))) mkdir(dirname($file), 0755, true);
		file_put_contents($file, json_encode($stored, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	/* -------------- The secret, encrypted --------------*/

	private static function key(bool $create = false): ?string
	{
		$file = kirby()->root('config') . '/formwizard.key';
		if (is_file($file)) {
			$key = base64_decode(trim((string) file_get_contents($file)), true);
			if ($key !== false && strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) return $key;
		}
		if (!$create) return null;
		$key = sodium_crypto_secretbox_keygen();
		file_put_contents($file, base64_encode($key) . "\n");
		return $key;
	}

	private static function encrypt(string $plain): string
	{
		$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key(true)));
	}

	/** The captcha's secret key – null without one or when it can't be read */
	public static function secret(): ?string
	{
		$stored = self::stored()['captchaSecret'] ?? '';
		$key = self::key();
		$raw = $stored !== '' ? base64_decode($stored, true) : false;
		if ($raw === false || $key === null || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
		$plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);
		return $plain === false ? null : $plain;
	}
}
