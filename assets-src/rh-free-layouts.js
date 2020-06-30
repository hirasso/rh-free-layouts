/** 
 * jQuery Plugin: free layout
 * Author: Rasso Hilber
 * Author URI: https://rassohilber.com
 */

global.jQuery = $ = window.jQuery;

import './scss/rh-free-layouts.scss';
import feather from 'feather-icons';

class FreeLayoutsEditMode {

  constructor( options = {} ) {
    
    this.options = $.extend({}, {
      containerSelector: 'body',
      groupSelector: 'body',
    }, RHFL.options);
    
    this.$items = $(this.options.containerSelector).find('.free-layout_item');
    this.initEditMode();
  }
  /**
   * Inits the edit mode
   */
  initEditMode() {

    this.$items.each((index, el) => {
      let $el = $(el);
      
      // do not instanciate twice
      if( $el.hasClass('ui-draggable') ) {
        return true;
      }
      
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
        containment: "#rhfl-containment",
        
      });

      $el.resizable({
        autoHide: true,
        handles: 'e, w',
        stop: () => this.afterEditLayout( $el )
      })

      $el.find('.ui-resizable-w').html(feather.icons['arrow-left'].toSvg());
      $el.find('.ui-resizable-e').html(feather.icons['arrow-right'].toSvg());
      $el.find('.ui-resizable-handle').mousedown(e => {
        let currentDirection = $(e.currentTarget).hasClass('ui-resizable-w') ? 'w' : 'e';
        $el.attr('data-active-handle', currentDirection);
      })
    });

  }

  /**
   * Injects the draggable containment element
   * @param {*} $el 
   * @param {*} index 
   */
  injectDraggableContainment( $el, index ) {
    let marginTop = parseFloat( $el.css('margin-top') );
    let marginLeft = parseFloat( $el.css('margin-left') );
    $el.css({
      top: marginTop + parseFloat( $el.css('top') ),
      left: marginLeft + parseFloat( $el.css('left') ),
      margin: 0
    });

    this.cleanupHelperElements();

    let $containmentDiv = $('<div id="rhfl-containment"></div>');
    let $parent = $el.parent();

    let $spacer = $('<div id="rhfl-spacer"></div>');
    $spacer.css({
      marginTop: marginTop,
      pointerEvents: 'none',
    }).insertAfter($el);
    
    let rect = {
      top: $parent.offset().top,
      left: $parent.offset().left,
      width: $parent.width(),
      height: $el.height() + 600,
    }

    let $previousItem = this.getPreviousItemInGroup( $el, index );
    if( $previousItem ) {
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
    $('body').append( $containmentDiv );
  }

  /**
   * Find previous item in Group
   * @param {*} currentIndex 
   */
  getPreviousItemInGroup( $currentItem, currentIndex ) {
    // early return for first of all items
    if( currentIndex === 0 ) return false;
    if( $currentItem.data('rhfl-group-start') ) return false;
    let $currentGroup = $currentItem.parents(`${this.options.groupSelector}:first`);
    for( let i = currentIndex-1; i >= 0; i--) {
      let $previousItem = this.$items.eq(i);
      if( $previousItem.parents(`${this.options.groupSelector}:first`).is($currentGroup) ) {
        return $previousItem;
      }
    }
    return false;
  }

  /**
   * Cleans up helper elements
   */
  cleanupHelperElements() {
    $('#rhfl-containment, #rhfl-spacer').remove();
  }

  /**
   * Custom layout item actions (reset/full-width)
   * @param {*} $el 
   */
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

  /**
   * Fired after a layout item has been edited
   * @param {*} $el 
   */
  afterEditLayout( $el ) {
    this.convertAndSaveLayoutItem( $el );
    this.cleanupHelperElements();
    $el.trigger('layout:updated');
  }

  /**
   * Resets a layout item
   * @param {*} $el 
   */
  resetLayoutItem( $el ) {
    $el.removeAttr('style');
    this.afterEditLayout( $el );
  }

  /**
   * Sets a layout item to full width
   * @param {*} $el 
   */
  setItemFullWidth( $el ) {
    $el.css({
      marginLeft: "0%",
      width: "100%"
    })
    this.afterEditLayout( $el );
  }

  /**
   * Converts a layout item position and saves it
   * @param {*} $el 
   */
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

  /**
   * Updates the database
   * @param {*} layoutId 
   * @param {*} postId 
   * @param {*} css 
   */
  updateDatabase( layoutId, postId, css = '' ) {

    $.ajax({
      method: "post",
      url: RHFL.ajaxUrl,
      data: {
        action: 'update_free_layout',
        layout_id: layoutId,
        post_id: postId,
        css: css
      },
      success: response =>  {
        this.showNotification( response );
      }
    });
  }

  showNotification( response ) {
    let message = ((response || {}).data || {}).message || 'Database updated';
    clearTimeout( this.notificationsTimeout );
    $('.free-layout_notification').remove();
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

}

/**
 * Register for direct access to class
 */
RHFL.initEditMode = (selector) => {
  let freeLayouts = new FreeLayoutsEditMode(selector);
};