# How to use

- Activate the plugin
- Add a field with type `Free Layout` to your ACF `Flexible Content` or `Repeater`.
- Optionally add a field with type `Reset Free Layouts` to your post

### Wrap your module or repeater row like this:

```php
<?php ob_start(); ?>
<div class="my-module">This module will support free layout</div>
<?php echo function_exists('rhfl') ? rhfl()->wrap_item( $value, ob_get_clean() ) : ob_get_clean() ?>
```

Initiate the edit mode from your php template (after all items have been rendered):

```php
if( function_exists('rhfl') ) rhfl()->get_edit_mode_js();
```

For full control over context and options, you can also initiate RHFL using JavaScript:

```js

if (window.RHFL != null) {
  window.RHFL.initEditMode({
    containerElement: document.querySelector('.my-free-layout'),
    groupSelector: '.my-group'
  });
}
```