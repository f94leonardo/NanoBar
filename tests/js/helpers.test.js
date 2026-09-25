'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const {
	contrastColor,
	isInternalQuickLinkUrl,
	relativizeQuickLinkUrl,
} = require('../../assets/js/admin-helpers.js');

const here = { origin: 'https://site.test', hostname: 'site.test' };
const contrast = {
	weights: { red: 299, green: 587, blue: 114 },
	threshold: 128,
	on_light: '#1d2327',
	on_dark: '#ffffff',
};

test('relative paths are internal', () => {
	assert.equal(isInternalQuickLinkUrl('/wp-admin/edit.php', here), true);
	assert.equal(isInternalQuickLinkUrl('/page?x=1#a', here), true);
	assert.equal(isInternalQuickLinkUrl('?x=1', here), true);
	assert.equal(isInternalQuickLinkUrl('contact/', here), false);
	assert.equal(isInternalQuickLinkUrl('example.com/page', here), false);
});

test('same-site absolute URLs are internal, other hosts are not', () => {
	assert.equal(isInternalQuickLinkUrl('https://site.test/x', here), true);
	assert.equal(isInternalQuickLinkUrl('https://SITE.test/x', here), true);
	assert.equal(isInternalQuickLinkUrl('https://evil.test/x', here), false);
});

test('protocol-relative and script schemes are never internal', () => {
	assert.equal(isInternalQuickLinkUrl('//evil.test/x', here), false);
	assert.equal(isInternalQuickLinkUrl('javascript:alert(1)', here), false);
	assert.equal(isInternalQuickLinkUrl('data:text/html,x', here), false);
	assert.equal(isInternalQuickLinkUrl('', here), false);
});

test('same-site absolute URLs are relativized, keeping query and hash', () => {
	assert.equal(
		relativizeQuickLinkUrl(
			'https://site.test/wp-admin/font-library.php?p=%2Ffont-list',
			here
		),
		'/wp-admin/font-library.php?p=%2Ffont-list'
	);
	assert.equal(
		relativizeQuickLinkUrl('https://site.test/a/b?x=1#h', here),
		'/a/b?x=1#h'
	);
});

test('relativizing never produces a protocol-relative path', () => {
	const out = relativizeQuickLinkUrl('https://site.test//evil.test/x', here);
	assert.equal(out.startsWith('//'), false);
	assert.equal(out, '/evil.test/x');
});

test('off-site, relative and protocol-relative values are unchanged', () => {
	assert.equal(
		relativizeQuickLinkUrl('https://evil.test/x', here),
		'https://evil.test/x'
	);
	assert.equal(relativizeQuickLinkUrl('/already', here), '/already');
	assert.equal(
		relativizeQuickLinkUrl('//evil.test/x', here),
		'//evil.test/x'
	);
	assert.equal(relativizeQuickLinkUrl('  ', here), '');
});

test('contrast color picks a readable text color', () => {
	assert.equal(contrastColor('#ffffff', contrast), '#1d2327');
	assert.equal(contrastColor('#000000', contrast), '#ffffff');
	assert.equal(contrastColor('#fff', contrast), '#1d2327');
	assert.equal(contrastColor('nonsense', contrast), '#ffffff');
});
