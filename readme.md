# How to use

- Activate the plugin
- Add a field with type `Free Layout` to your ACF `Flexible Content` or `Repeater`.
- Optionally add a field with type `Reset Free Layouts` to your post

## Wrap your module or repeater row like this:

```php
<?php ob_start(); ?>
<div class="my-module">This module will support free layout</div>
<?php echo rhfl_wrap_item( $value, ob_get_clean(), $post_id ) ?>
```

## Initiate the edit mode from your JS:

```javascript
try { $(document).freelayouts() } catch(e) { console.warn( e ) }
```
...or

```javascript
try { new RHFL.Editor() } catch( e ) { console.warn( e ) }
```
