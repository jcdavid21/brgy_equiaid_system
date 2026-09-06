<?php
require __DIR__ . '/../backend/tags.php';
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
check(tag_slug('  Senior Citizen ') === 'senior-citizen', 'slug normalization failed');
check(tag_valid_color('#17A84e') && !tag_valid_color('green'), 'color validation failed');
$tags = tag_normalize([' PWD ', 'PWD', 'Solo   Parent']);
check(count($tags) === 2 && $tags['solo-parent'] === 'Solo Parent', 'tag normalization/deduplication failed');
check(tag_normalize(['<b>High Risk</b>'])['high-risk'] === 'High Risk', 'tag sanitization failed');
try { tag_normalize([str_repeat('x', 81)]); check(false, 'long tag accepted'); } catch (InvalidArgumentException $e) {}
try { tag_normalize(range(1, 13)); check(false, 'non-text tags accepted'); } catch (InvalidArgumentException $e) {}
try { tag_assert_object('unknown'); check(false, 'unknown object accepted'); } catch (InvalidArgumentException $e) {}
echo "Tag helper tests passed.\n";
