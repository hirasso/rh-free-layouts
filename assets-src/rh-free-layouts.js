/** 
 * jQuery Plugin: free layout
 * Author: Rasso Hilber
 * Author URI: https://rassohilber.com
 */


global.jQuery = $ = window.jQuery;

import './scss/rh-free-layouts.scss';
import plugin, { PluginBase } from './js/plugin';
import feather from 'feather-icons';

export default class PluginClass extends PluginBase {

  constructor( el, options ) {

    super( el, options );
    
    this.$items = this.$el.find(options.itemSelector);
    if( !this.$items.length ) {
      this.destroy();
      return;
    }

    if( this.$el.hasClass('free-layout') ) {
      return;
    }

    this.$el.addClass('free-layout');

    this.initEditMode();

  }

  initEditMode() {

    this.$items.each((index, el) => {
      let $el = $(el);
      
      // do not instanciate twice
      if( $el.hasClass('ui-draggable') ) {
        return true;
      }

      let elementId = $el.data('layout-id');
      
      $el.find('.free-layout_item_handle').remove();
      $el.append(`<div class="free-layout_item_handle">
        <a data-layout-action='full-width' href='#'>Full Width</a>
        <a data-layout-action='reset' href='#'>Reset Layout</a>
        </div>
      `);
      $el.attr('data-free-layout-index', index);
      
      this.initLayoutActions($el);
      
      $el.draggable({
        iframeFix: true,
        cancel: 'a',
        helper: (event) => {
          this.injectDraggableContainment( $el, index );
          return $el;
        },
        scroll: true,
        stop: () => this.afterEditLayout( $el ),
        containment: "#div_containment",
        
      });

      $el.resizable({
        autoHide: true,
        handles: 'e, w',
        stop: () => this.afterEditLayout( $el ),
        resize: (event, ui) => {
          let marginLeft = parseFloat($el.css('marginLeft'));
          let direction = $el.data('resizing-direction');
          
          if( marginLeft ) {
            ui.position.left += marginLeft;
            ui.originalPosition.left += marginLeft;
            $el.css('margin-left', '');
          }
          ui.position.left = Math.max( 0, ui.position.left );
          
          if( event.altKey ) {
            let leftDiff = ui.originalPosition.left - ui.position.left;
            let widthDiff = ui.originalSize.width - ui.size.width;
            
            switch( direction ) {
              case 'w':
                ui.size.width += leftDiff;
                ui.size.width = Math.min($el.parent().width() - ui.position.left, ui.size.width);
                break;
              case 'e':
                // TODO support centered resizing on 'e' handle
                break;
            }
          }

        }
      })

      $el.find('.ui-resizable-w').html(feather.icons['arrow-left'].toSvg());
      $el.find('.ui-resizable-e').html(feather.icons['arrow-right'].toSvg());
      $el.find('.ui-resizable-handle').mousedown(e => {
        let currentDirection = $(e.currentTarget).hasClass('ui-resizable-w') ? 'w' : 'e';
        $el.attr('data-resizing-direction', currentDirection);
      })
    });

  }

  injectDraggableContainment( $el, index ) {
    $el.css({
      top: parseFloat( $el.css('margin-top') ) + parseFloat( $el.css('top') ),
      left: parseFloat( $el.css('margin-left') ) + parseFloat( $el.css('left') ),
      margin: 0
    });

    $('#div_containment').remove();

    let $containmentDiv = $('<div id="div_containment"></div>');
    let $parent = $el.parent();
    let rect = {
      top: $parent.offset().top,
      left: $parent.offset().left,
      width: $parent.width(),
      height: $el.height() + 300,
    }

    if( index > 0 ) {
      let $previousItem = this.$items.eq(index - 1);
      rect.top = $previousItem.offset().top;
      rect.height += $previousItem.height();
    }

    $containmentDiv.css({
      top: rect.top,
      left: rect.left,
      width: rect.width,
      height: rect.height,
      position: 'absolute',
      zIndex: 2,
      pointerEvents: 'none',
      background: 'rgba(0,200,0,0.1)',
    })
    $containmentDiv.appendTo($('body'));
  }

  initLayoutActions( $el ) {
    $el.find('[data-layout-action]').each((i, el) => {
      let $link = $(el);
      let action = $link.data('layout-action');
      $link.click(e => {
        e.preventDefault();
        switch( action ) {
          case 'reset':
            this.resetLayoutItem($el);
            break;
          case 'full-width':
            this.setItemFullWidth($el);
            break;
        }
      });
    })
  }

  afterEditLayout( $el ) {
    $('#div_containment').remove();
    this.convertAndSaveLayoutItem( $el );
    $el.trigger('layout:updated');
  }

  resetLayoutItem( $el ) {
    $el.removeAttr('style');
    this.afterEditLayout( $el );
  }

  setItemFullWidth( $el ) {
    $el.css({
      marginLeft: "0%",
      width: "100%"
    })
    this.afterEditLayout( $el );
  }

  convertAndSaveLayoutItem( $el ) {

    let layoutId = $el.attr('data-layout-id');
    let postId = $el.attr('data-post-id');
    let css = $el.attr('style');
    
    if( !css ) {
      this.updateDatabase(
        layoutId,
        postId
      );
      return;
    }

    let $parent = $el.parent();

    let offsetTop = $el.offset().top - $parent.offset().top;
    let offsetLeft = $el.offset().left - $parent.offset().left;

    let parentWidth = $parent.width();

    let newWidth = parseFloat($el.outerWidth() / parentWidth * 100).toFixed(2);
    let newMarginTop = parseFloat(offsetTop / parentWidth * 100).toFixed(2);
    let newMarginLeft = parseFloat(offsetLeft / parentWidth * 100).toFixed(2);

    // normalize the new values
    newWidth = Math.max(10, Math.min(100, newWidth));
    newMarginLeft = Math.max(0, Math.min(90, newMarginLeft));

    let newCSS = {
      'margin-top': `${newMarginTop}%`,
      'margin-left': `${newMarginLeft}%`,
      'width': `${newWidth}%`
    };

    $el
      .removeAttr('style')
      .css(newCSS);
    
    this.updateDatabase(
      layoutId,
      postId,
      newCSS
    );
  
  }

  updateDatabase( layoutId, postId, css = '' ) {

    $.ajax({
      method: "post",
      url: this.options.ajaxUrl,
      data: {
        action: 'update_free_layout',
        layout_id: layoutId,
        post_id: postId,
        css: css
      },
      success: response =>  {
        console.log(response);
        this.showNotification( response );
      }
    });
  }

  showNotification( response ) {
    let message = ((response || {}).data || {}).message || 'Database updated';
    clearTimeout( this.notificationsTimeout );
    let $notification = $('<div></div>');
    $notification
      .appendTo('body')
      .text(message)
      .addClass('free-layout_notification');
    setTimeout(() => {
       $notification.addClass('is-visible');
    }, 10);
    
    this.notificationsTimeout = setTimeout( () => {
      $notification.removeClass('is-visible');
      setTimeout(() => {
        $notification.remove();
      }, 500);
    }, 1500);
  }

  destroy() {
    super.destroy();
    this.$items.each((i, el) => {

    })
    $('.free-layout_notification').remove();
  }

}

/**
 * Defaults
 * @type {{color: string, status: number}}
 */
PluginClass.DEFAULTS = {
  itemSelector: '.free-layout_item',
  ajaxUrl: FreeLayouts.ajaxUrl
};

/**
 * make jQuery Plugin
 */
plugin('freelayout', PluginClass, true);
