<?php

use Kirby\Cms\Page;
use Kirby\Http\Remote;

/**
 * A captcha of a provider (Friendly Captcha, Cloudflare Turnstile,
 * hCaptcha), set up in Project Wizard › Forms › Settings, switched off per
 * form. The widget in the form, the answer checked with the provider when
 * sending. And the limit: at most n submissions in x minutes per visitor
 * (counted by a hash of the address, forgotten after the time).
 */
class fwCaptcha
{
	/** The providers: script, widget class, the field the answer comes in, the check */
	public const PROVIDERS = [
		'friendly' => [
			'script' => 'https://cdn.jsdelivr.net/npm/@friendlycaptcha/sdk@0.1/site.min.js',
			'module' => true,
			'class'  => 'frc-captcha',
			'field'  => 'frc-captcha-response',
			'verify' => 'https://global.frcapi.com/api/v2/captcha/siteverify',
		],
		'turnstile' => [
			'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
			'module' => false,
			'class'  => 'cf-turnstile',
			'field'  => 'cf-turnstile-response',
			'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
		],
		'hcaptcha' => [
			'script' => 'https://js.hcaptcha.com/1/api.js',
			'module' => false,
			'class'  => 'h-captcha',
			'field'  => 'h-captcha-response',
			'verify' => 'https://api.hcaptcha.com/siteverify',
		],
	];

	/** The provider for this form – null without one (none set up, keys missing, the form says no) */
	public static function provider(Page $form): ?string
	{
		$settings = fwSettings::get();
		$provider = $settings['captcha'];
		if ($provider === 'none' || !isset(self::PROVIDERS[$provider])) return null;
		if ($settings['captchaSiteKey'] === '' || fwSettings::secret() === null) return null;
		$c = $form->content()->get('captcha');
		return $c->isEmpty() || $c->toBool() ? $provider : null;
	}

	/** The widget and its script (the script once per page) */
	public static function widget(Page $form, string $id, ?string $error): string
	{
		$provider = self::provider($form);
		if ($provider === null) return '';
		static $scripts = [];
		$p    = self::PROVIDERS[$provider];
		$lang = kirby()->language()?->code() ?? kirby()->user()?->language() ?? 'de';
		$html = '<div data-formwizard-captcha data-width="full"' . ($error ? ' data-invalid' : '') . '>'
			. '<div class="' . $p['class'] . '" data-sitekey="' . esc(fwSettings::get()['captchaSiteKey'], 'attr') . '" data-lang="' . esc($lang, 'attr') . '" data-language="' . esc($lang, 'attr') . '" data-hl="' . esc($lang, 'attr') . '"></div>'
			. ($error ? '<span data-formwizard-error id="' . $id . '-captcha-error">' . esc(fwRender::error($error)) . '</span>' : '')
			. '</div>' . "\n";
		if (!isset($scripts[$provider])) {
			$scripts[$provider] = true;
			$html .= '<script src="' . $p['script'] . '"' . ($p['module'] ? ' type="module"' : ' async defer') . '></script>' . "\n";
		}
		return $html;
	}

	/** The answer checked with the provider: null when fine, else the error */
	public static function verify(Page $form, array $data): ?string
	{
		$provider = self::provider($form);
		if ($provider === null) return null;
		$p        = self::PROVIDERS[$provider];
		$response = trim((string) ($data[$p['field']] ?? ''));
		if ($response === '') return 'captcha';
		$secret   = (string) fwSettings::secret();
		$sitekey  = fwSettings::get()['captchaSiteKey'];
		try {
			$result = $provider === 'friendly'
				? Remote::request($p['verify'], [
					'method'  => 'POST',
					'data'    => json_encode(['response' => $response, 'sitekey' => $sitekey]),
					'headers' => ['Content-Type' => 'application/json', 'X-API-Key' => $secret],
					'timeout' => 8,
				])
				: Remote::request($p['verify'], [
					'method'  => 'POST',
					'data'    => http_build_query(['secret' => $secret, 'response' => $response, 'sitekey' => $sitekey, 'remoteip' => kirby()->visitor()->ip()]),
					'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
					'timeout' => 8,
				]);
			$json = $result->json();
		} catch (\Throwable $e) {
			// (the provider not reachable: no way to tell – rather no spam)
			kirby()->trigger('formwizard.failed', ['form' => $form, 'error' => 'Captcha: ' . $e->getMessage()]);
			return 'captcha';
		}
		return is_array($json) && ($json['success'] ?? false) === true ? null : 'captcha';
	}

	/* -------------- The limit --------------*/

	private static function visitor(): string
	{
		$salt = option('content.salt');
		return substr(hash('sha256', kirby()->visitor()->ip() . '|' . kirby()->root('index') . '|' . (is_string($salt) ? $salt : '')), 0, 32);
	}

	/** May this visitor send once more? */
	public static function allowed(): bool
	{
		$settings = fwSettings::get();
		if (!$settings['rate']) return true;
		$since = time() - $settings['rateMinutes'] * 60;
		$times = array_filter((array) kirby()->cache('kirbydesk.kirby-formwizard')->get('rate.' . self::visitor(), []), fn ($t) => $t > $since);
		return count($times) < $settings['rateLimit'];
	}

	/** A submission counted for this visitor */
	public static function count(): void
	{
		$settings = fwSettings::get();
		if (!$settings['rate']) return;
		$cache = kirby()->cache('kirbydesk.kirby-formwizard');
		$key   = 'rate.' . self::visitor();
		$since = time() - $settings['rateMinutes'] * 60;
		$times = array_values(array_filter((array) $cache->get($key, []), fn ($t) => $t > $since));
		$times[] = time();
		$cache->set($key, $times, $settings['rateMinutes']);
	}
}
