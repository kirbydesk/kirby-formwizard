<?php

// Config
$config   = pwConfig::load('pwform');
$settings = $config['content'];

// Custom Background
pwSnippet::customCss($block);

// The form chosen (active forms only) and the result of the last sending
$form   = fwRender::formOf($block->content()->get('form')->value());
$result = kirby()->session()->pull('formwizard.' . $block->id(), null);
$status = is_array($result) ? ($result['status'] ?? '') : '';

// Section + Grid open
echo pwSnippet::sectionOpen('form', $block, $settings);
echo pwSnippet::gridOpen($block);

// Tagline
if (!empty($settings['tagline'])):
	snippet('tagline', ['content' => $block]);
endif;

// Heading
if (!empty($settings['heading'])):
	snippet('heading', ['content' => $block]);
endif;

// Editor
if (!empty($settings['editor'])):
	snippet('editor', ['content' => $block]);
endif;

echo '<div data-block="items" id="formwizard-' . $block->id() . '">' . "\n";

if ($form === null):
	// (no form or a draft: a note for the editors only)
	if (kirby()->user()):
		echo '<div data-formwizard-message="note">' . esc(t('formwizard.block.noForm')) . '</div>' . "\n";
	endif;

elseif ($status === 'sent'):
	// instead of the form: the thanks of the block, else of the form
	$text = $block->content()->get('successText')->or($form->content()->get('successText'))->or(t('formwizard.success.text.default'))->value();
	echo '<div data-formwizard-message="sent" role="status">' . kirbytext(fwRender::fill(esc($text), $result['values'] ?? [])) . '</div>' . "\n";

elseif ($status === 'closed' || $form->content()->get('closed')->toBool()):
	$text = $form->content()->get('closedText')->or(t('formwizard.closed.text.default'))->value();
	echo '<div data-formwizard-message="closed">' . kirbytext(esc($text)) . '</div>' . "\n";

else:
	if ($status === 'failed'):
		echo '<div data-formwizard-message="failed" role="alert">' . kirbytext(esc(t('formwizard.failed'))) . '</div>' . "\n";
	elseif ($status === 'limited'):
		echo '<div data-formwizard-message="failed" role="alert">' . esc(t('formwizard.limited')) . '</div>' . "\n";
	elseif ($status === 'invalid'):
		echo '<div data-formwizard-message="invalid" role="alert">' . esc(t('formwizard.invalid')) . '</div>' . "\n";
	endif;
	echo fwRender::form($form, $block, $page, is_array($result) ? $result : []);
	echo fwRender::script();
endif;

echo '</div>' . "\n"; // End Items

// Close
echo pwSnippet::gridClose();
echo pwSnippet::sectionClose();
