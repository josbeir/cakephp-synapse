# Lists and Formatting

This document contains various list formats to test list marker removal.

## Unordered Lists

Basic bullet list:

* Item 1
* Item 2
* Item 3

Alternative markers:

- First item
- Second item
- Third item

Plus markers:

+ Alpha
+ Beta
+ Gamma

## Ordered Lists

Simple numbered list:

1. First step
2. Second step
3. Third step

Continue numbering:

4. Fourth step
5. Fifth step

## Nested Lists

* Parent item 1
  * Child item 1.1
  * Child item 1.2
* Parent item 2
  * Child item 2.1
    * Grandchild item 2.1.1

Mixed nesting:

1. Numbered parent
   * Bullet child
   * Another bullet
2. Second numbered
   1. Nested number
   2. Another nested

## Task Lists

- [ ] Uncompleted task
- [x] Completed task
- [ ] Another uncompleted task

## Lists with Content

* **Bold list item** with emphasis
* List item with `inline code`
* List item with [a link](https://example.com)

Complex list:

1. First item with multiple lines
   of content that wraps around
2. Second item with code: `example()`
3. Third item

## Horizontal Rules

Lists separated by horizontal rules:

* Item A
* Item B

---

* Item C
* Item D

***

* Item E
* Item F

___

## Mixed Content

Text before list:

* List item one
* List item two

Text after list.

Another paragraph with 1. not a list because no space.

But this is:

1. Proper list item
2. Another proper item

## Clean Result

After processing, list content should remain but markers (* - + 1. 2. etc.) should be removed.