# HTML Content Test

This document contains HTML elements to test HTML removal.

## Styled Content

<div class="note">
This is a note with <strong>bold</strong> text and <em>italic</em> text.
</div>

<p class="warning">
Warning message with <span class="highlight">highlighted</span> content.
</p>

## Comments

<!-- This is an HTML comment that should be removed -->

Regular text should remain visible.

<!-- 
Multi-line comment
that spans several lines
should also be removed
-->

## Inline HTML

This paragraph has <strong>inline bold</strong> and <code>inline code</code> tags.

<div>
Nested content with <a href="https://example.com">links</a> inside.
</div>

## Clean Content

After processing, only the text content should remain, without any HTML tags or comments.