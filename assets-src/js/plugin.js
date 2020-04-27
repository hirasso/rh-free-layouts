
var jQuery = $ = global.jQuery;

/**
* Generate a jQuery plugin
* https://gist.github.com/monkeymonk/c08cb040431f89f99928132ca221d647
*
* @param pluginName [string] Plugin name
* @param className [object] Class of the plugin
* @param shortHand [bool] Generate a shorthand as $.pluginName
*
* @example
* import plugin, { PluginBase } from 'plugin';
*
* class MyPlugin extends PluginBase {
*     constructor(element, options) {
*        super( element, options );
*         // ...
*     }
* }
*
* MyPlugin.DEFAULTS = {};
*
* plugin('myPlugin', MyPlugin');
*/

export default function plugin(pluginName, className, shortHand = false) {
let dataName = `${pluginName}`;
let old = $.fn[pluginName];

$.fn[pluginName] = function (option) {
  return this.each(function () {
    let $this = $(this);
    let data = $this.data(dataName);
    let options = $.extend({}, className.DEFAULTS, $this.data(), typeof option === 'object' && option);
    
    if (!data) {
      $this.data(dataName, (data = new className(this, options)));
    }
    
    if (typeof option === 'string') {
      data[option]();
    }
  });
};

// - Short hand
if (shortHand) {
  $[pluginName] = (options) => $({})[pluginName](options);
}

// - No conflict
$.fn[pluginName].noConflict = () => $.fn[pluginName] = old;
}


/**
* PluginBase to be extended with custom functionality.
* Contains helper functions for adding events and automatically destroying itself.
* Author: Rasso Hilber
* Author URI: https://rassohilber.com
*/
export class PluginBase {
  constructor(el, options) {
    this.el = el;
    this.$el = $(el);
    this.options = options;
    this.handleEvent = this.handleEvent.bind(this);
    this.events = [];
  }
  /**
  * Add an event that will be removed if destroy() is called
  * @param {jQuery object} $el the element the event should be attached to
  * @param {string} event type, e.g. 'mousemove' or 'resize'
  */
  addEvent( $el, type, options = false ) {
    if( options ) {
      $el.on( type, options, this.handleEvent );
    } else {
      $el.on(type, this.handleEvent);
    }
    this.events.push({
      $el: $el,
      type: type
    })
  }
  /**
  * Remove an event
  * @param {jQuery object} $el the element
  * @param {string} event type, e.g. 'mousemove' or 'resize'
  */
  removeEvent( $el, type ) {
    $el.off(type, this.handleEvent);
  }
  /**
  * Handles all events and calls corresponding function if present in class
  * @param {string} event type
  */
  handleEvent( e ) {
    if( this.maybeDestroy() ) {
      return;
    }
    let method = 'on' + e.type;
    
    if (this[method]) {

      if(e.handleObj.data && e.handleObj.data.throttle) {
        
        clearTimeout(e.handleObj.timeout);
        e.handleObj.timeout = setTimeout(() => { this[method](e); }, e.handleObj.data.throttle);
        
        if(!e.handleObj.lastCall || Date.now() - e.handleObj.lastCall >= e.handleObj.data.throttle) {
          e.handleObj.lastCall = Date.now();
          this[method](e);
        }
        
      } else {
        this[method](e);
      }

    }
  }
  /**
  * Checks if el is still in DOM. If not, destroys
  */
  maybeDestroy() {
    if( !document.body.contains( this.el ) ) {
      this.destroy();
      return true;
    }
    return false;
  }
  /**
  * Removes all registered event listeners
  */
  destroy() {
    // return early if no events
    if( !this.events ) {
      return;
    }
    this.events.forEach(item => {
      item.$el.off(item.type, this.handleEvent );
    });
    this.events = null;
  }
}