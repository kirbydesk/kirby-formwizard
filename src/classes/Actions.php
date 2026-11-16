<?php

use Kirby\Cms\Page;
use Kirby\Http\Remote;
use Kirby\Toolkit\V;

/**
 * What happens after sending, in this order: the mail to the team (to the
 * recipients of the form, else the project's – or by a choice in the form),
 * the confirmation to the sender, the webhook. Each writes a line into the
 * submission's log; the mails go through the Project Wizard's mail server
 * (pwMail).
 */
class fwActions
{
	/**
	 * Runs the actions of a form for a submission – returns the submission
	 * with its log, and whether the team got it ('team' => true|false|null)
	 */
	public static function run(Page $form, Page $page, array $submission): array
	{
		$c   = $form->content();
		$log = [];
		$submission['team'] = null;

		if ($c->get('mailTeam')->isEmpty() || $c->get('mailTeam')->toBool()) {
			[$ok, $text] = self::team($form, $page, $submission);
			$submission['team'] = $ok;
			$log[] = ['ok' => $ok, 'text' => $text, 'time' => date('c')];
		}
		if ($c->get('confirm')->toBool()) {
			[$ok, $text] = self::confirm($form, $submission);
			if ($text !== null) $log[] = ['ok' => $ok, 'text' => $text, 'time' => date('c')];
		}
		if ($c->get('webhook')->toBool() && $c->get('webhookUrl')->isNotEmpty()) {
			[$ok, $text] = self::webhook($form, $page, $submission);
			$log[] = ['ok' => $ok, 'text' => $text, 'time' => date('c')];
		}

		$submission['log'] = array_merge($submission['log'] ?? [], $log);
		return $submission;
	}

	/* -------------- The mail to the team --------------*/

	/** Who gets it: the routes that match, else the form's recipients, else the project's */
	public static function recipients(Page $form, array $submission): array
	{
		$values = array_column($submission['fields'], 'value', 'key');
		$byId   = [];
		foreach (fwRender::fields($form) as $field) $byId[$field['id']] = $field['key'];

		$to = [];
		foreach ($form->content()->get('routes')->toStructure() as $route) {
			[$id, $value] = array_pad(explode(':', (string) $route->content()->get('when')->value(), 2), 2, '');
			$given = $values[$byId[$id] ?? ''] ?? null;
			$match = is_array($given) ? in_array($value, $given, true) : $given === $value;
			if ($match) $to = array_merge($to, self::addresses((string) $route->content()->get('to')->value()));
		}
		if ($to === []) $to = self::addresses((string) $form->content()->get('recipients')->value());
		if ($to === [] && class_exists('pwMail')) $to = pwMail::recipient();
		return array_values(array_unique($to));
	}

	private static function team(Page $form, Page $page, array $submission): array
	{
		$to = self::recipients($form, $submission);
		if ($to === []) return [false, t('formwizard.log.noRecipient')];

		$subject = self::fill(
			$form->content()->get('mailSubject')->or(t('formwizard.team.subject.default'))->value(),
			$form, $page, $submission
		);
		$reply = self::senderAddress($submission);
		$panel = ($submission['stored'] ?? true) ? kirby()->url('panel') . '/formwizard/submissions/' . $submission['id'] : null;

		$intro = tt('formwizard.mail.intro', null, ['form' => $form->title()->value(), 'page' => $page->title()->value()]);
		$text = $intro . "\n\n" . self::listText($submission) . "\n\n— " . $page->url() . ($panel ? "\n" . t('formwizard.mail.panel') . ': ' . $panel : '');
		$html = '<p style="font:14px/1.5 sans-serif;margin:0 0 12px">' . esc($intro) . '</p>'
			. self::listHtml($submission)
			. '<p style="font:12px/1.5 sans-serif;color:#666;margin:16px 0 0"><a href="' . esc($page->url(), 'attr') . '">' . esc($page->url()) . '</a>'
			. ($panel ? ' · <a href="' . htmlspecialchars($panel) . '">' . esc(t('formwizard.mail.panel')) . '</a>' : '') . '</p>';

		return self::mail([
			'to'      => $to,
			'replyTo' => $reply,
			'subject' => $subject,
			'body'    => ['text' => $text, 'html' => $html],
		], tt('formwizard.log.team', null, ['to' => implode(', ', $to)]));
	}

	/* -------------- The confirmation to the sender --------------*/

	private static function confirm(Page $form, array $submission): array
	{
		$to = self::senderAddress($submission);
		// (no e-mail address given: nothing to confirm – and nothing to log)
		if ($to === null) return [true, null];

		$c       = $form->content();
		$page    = page($submission['page']['id'] ?? '') ?? site()->homePage();
		$subject = self::fill($c->get('confirmSubject')->or(t('formwizard.confirm.subject.default'))->value(), $form, $page, $submission);
		$intro   = self::fill($c->get('confirmText')->or(t('formwizard.confirm.text.default'))->value(), $form, $page, $submission);
		$copy    = $c->get('confirmCopy')->isEmpty() || $c->get('confirmCopy')->toBool();

		$text = $intro . ($copy ? "\n\n" . t('formwizard.mail.copy') . "\n\n" . self::listText($submission) : '');
		$html = '<p style="font:14px/1.5 sans-serif;margin:0 0 16px">' . nl2br(esc($intro)) . '</p>'
			. ($copy ? '<p style="font:13px/1.5 sans-serif;color:#666;margin:0 0 8px">' . esc(t('formwizard.mail.copy')) . '</p>' . self::listHtml($submission) : '');

		$replyTo = self::recipients($form, $submission)[0] ?? null;
		return self::mail(array_filter([
			'to'      => $to,
			'replyTo' => $replyTo,
			'subject' => $subject,
			'body'    => ['text' => $text, 'html' => $html],
		]), tt('formwizard.log.confirm', null, ['to' => $to]));
	}

	/* -------------- The webhook --------------*/

	private static function webhook(Page $form, Page $page, array $submission): array
	{
		$url = (string) $form->content()->get('webhookUrl')->value();
		if (!V::url($url) || !preg_match('#^https?://#', $url)) return [false, t('formwizard.log.webhook.url')];
		$payload = [
			'form'      => ['id' => $form->slug(), 'title' => $form->title()->value()],
			'page'      => ['id' => $page->id(), 'title' => $page->title()->value(), 'url' => $page->url()],
			'submitted' => $submission['created'],
			'language'  => $submission['language'],
			'fields'    => array_column($submission['fields'], 'value', 'key'),
			'labels'    => array_column($submission['fields'], 'label', 'key'),
		];
		try {
			$response = Remote::request($url, [
				'method'  => 'POST',
				'data'    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
				'headers' => ['Content-Type' => 'application/json', 'User-Agent' => 'kirby-formwizard'],
				'timeout' => 8,
			]);
			$code = $response->code();
		} catch (\Throwable $e) {
			return [false, tt('formwizard.log.webhook.failed', null, ['error' => $e->getMessage()])];
		}
		$host = parse_url($url, PHP_URL_HOST);
		return $code >= 200 && $code < 300
			? [true, tt('formwizard.log.webhook', null, ['host' => $host, 'code' => $code])]
			: [false, tt('formwizard.log.webhook.failed', null, ['error' => $host . ' – HTTP ' . $code])];
	}

	/* -------------- Helpers --------------*/

	/** A mail through the project's mail server: [ok, log text] */
	private static function mail(array $mail, string $done): array
	{
		if (!class_exists('pwMail') || !pwMail::ready()) return [false, t('formwizard.log.noServer')];
		try {
			pwMail::send($mail);
		} catch (\Throwable $e) {
			return [false, tt('formwizard.log.failed', null, ['what' => $done, 'error' => $e->getMessage()])];
		}
		return [true, $done];
	}

	/** The sender's address: the first e-mail field filled in */
	public static function senderAddress(array $submission): ?string
	{
		foreach ($submission['fields'] as $f) {
			if ($f['type'] === 'fwemail' && is_string($f['value']) && V::email($f['value'])) return $f['value'];
		}
		return null;
	}

	/** "a@x.de, b@x.de" → the valid addresses */
	private static function addresses(string $list): array
	{
		return array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $list)), fn ($a) => V::email($a)));
	}

	/** {{ key }} by what was entered, {{ form }}, {{ page }}, {{ site }} */
	public static function fill(string $text, Page $form, Page $page, array $submission): string
	{
		$values = array_column($submission['fields'], 'display', 'key') + [
			'form' => $form->title()->value(),
			'page' => $page->title()->value(),
			'site' => site()->title()->value(),
		];
		return trim(preg_replace_callback('/\{\{?\s*([a-z0-9_-]+)\s*\}?\}/i', fn ($m) => (string) ($values[$m[1]] ?? ''), $text));
	}

	private static function listText(array $submission): string
	{
		$lines = [];
		foreach ($submission['fields'] as $f) {
			if ($f['type'] === 'fwhidden' || $f['display'] === '') continue;
			$lines[] = $f['label'] . ':' . ($f['type'] === 'fwtextarea' ? "\n" : ' ') . $f['display'];
		}
		foreach ($submission['files'] ?? [] as $file) $lines[] = t('formwizard.submission.files') . ': ' . $file['name'];
		return implode("\n", $lines);
	}

	private static function listHtml(array $submission): string
	{
		$rows = '';
		foreach ($submission['fields'] as $f) {
			if ($f['type'] === 'fwhidden' || $f['display'] === '') continue;
			$rows .= '<tr><td style="padding:6px 16px 6px 0;color:#666;vertical-align:top;white-space:nowrap">' . esc($f['label']) . '</td>'
				. '<td style="padding:6px 0;vertical-align:top">' . nl2br(esc($f['display'])) . '</td></tr>';
		}
		return '<table style="font:14px/1.5 sans-serif;border-collapse:collapse">' . $rows . '</table>';
	}
}
