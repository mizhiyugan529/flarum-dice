import {VnodeDOM} from 'mithril';
import {extend, override} from 'flarum/common/extend';
import app from 'flarum/forum/app';
import Post from 'flarum/common/models/Post';
import extractText from 'flarum/common/utils/extractText';
import ItemList from 'flarum/common/utils/ItemList';
import CommentPost from 'flarum/forum/components/CommentPost';
import TextEditor from 'flarum/common/components/TextEditor';
import TextEditorButton from 'flarum/common/components/TextEditorButton';


function configureTooltip(element: Element) {
  $(element).tooltip({
    container: document.body,
    placement: 'right',
  });
}

app.initializers.add('roll-die-20', () => {
  override(Post.prototype, 'contentHtml', function (this: Post, original: () => string) {
    const contentHtml = original();
    const rollsAsString = this.attribute('diceRolls20');

    if (!rollsAsString) {
      return contentHtml;
    }

    const rolls = rollsAsString.split(' ');
    let index = 0;

    // We will match every die emoji alone on its line
    // A line might be wrapped in a paragraph, or have newlines before or after
    // When the <br> is before, there's an additional newline in the HTML in between
    // The first emoji needs to be outside of the [] rule because it's multi-byte
    // Positive lookahead is necessary otherwise a series of emojis separated only by newlines will only match 1/2
    var regObj = new RegExp("\\[dice](\\d?d\\d+|\\d+)((\\+|-)(\\d?d\\d+|\\d+))?((\\+|-)(\\d?d\\d+|\\d+))?\\[/dice]","gmi");
    try{
    return contentHtml.replace(regObj, (match, before) => {
      const number = rolls[index++];
      console.log(before);
      const span = document.createElement('span');
      span.className = 'roll-a-die-20';
      span.dataset.number = number + '';
      span.textContent = number;

      return span.outerHTML;
    });}catch (e) {
      return contentHtml
    }
  });

  extend(CommentPost.prototype, ['oncreate', 'onupdate'], function (returnValue: any, vnode: VnodeDOM) {
    vnode.dom.querySelectorAll('.roll-a-die-20').forEach(element => {
      configureTooltip(element);
    });
  });

  // @ts-ignore global variables and many untyped parameters
  override(s9e.TextFormatter, 'preview', (original, text, element) => {
    original(text, element);

    let walk;
    let node;

    // Clean up existing DOM to remove any now invalid span
    walk = document.createTreeWalker(element);
    while (node = walk.nextNode()) {
      if (node instanceof HTMLElement && node.classList.contains('roll-a-die-20')) {
        const text = document.createTextNode(node.textContent || '');
        node.parentNode!.replaceChild(text, node);
      }
    }

    // Convert valid text nodes into the preview span
    walk = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
    const replaceQueue = [];
    while (node = walk.nextNode()) {
      // Skip if no text content to please typescript
      if (!node.textContent) {
        continue;
      }

      // Skip if parent is already the result of a span conversion
      if ((node.parentNode as HTMLElement).classList.contains('roll-a-die-20')) {
        continue;
      }

    }

  });

  extend(TextEditor.prototype, 'toolbarItems', function (this: TextEditor, items: ItemList) {
    items.add('roll-die-20', m(TextEditorButton, {
      icon: 'fas fa-dice',
      onclick: () => {
        this.attrs.composer.editor.insertAtCursor('[dice][/dice]');
        const range = this.attrs.composer.editor.getSelectionRange();
        this.attrs.composer.editor.moveCursorTo(range[1] - 7);
      },
    }, app.translator.trans('annonny-dice.forum.tooltip.preview')));
  },999);
}, 999);
// Priority must be lower than Flarum's flarum-emoji, which has default priority, so any value could work
