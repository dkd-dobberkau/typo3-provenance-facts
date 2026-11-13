/**
 * CKEditor 5 plugin "ProvenanceFact": select the words that state a fact,
 * press "Fact", describe it, and a fact record is created for the content
 * element. The text itself is not changed; the page marks the quote when it
 * is rendered.
 */
import { Plugin } from '@ckeditor/ckeditor5-core';
import { ButtonView } from '@ckeditor/ckeditor5-ui';
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Severity from '@typo3/backend/severity.js';

const FIELD_NAME = /^data\[tt_content\]\[(\d+)\]\[bodytext\]$/;
const ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M3 4.5h14v9H8l-3.5 3v-3H3z" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="13.5" cy="9" r="1.6" fill="currentColor"/></svg>';

export class ProvenanceFact extends Plugin {
  static get pluginName() {
    return 'ProvenanceFact';
  }

  init() {
    const editor = this.editor;
    editor.ui.componentFactory.add('provenanceFact', (locale) => {
      const button = new ButtonView(locale);
      button.set({ label: 'Fact', icon: ICON, tooltip: 'Make the selected words a citable fact', withText: true });
      // selection.isCollapsed is a plain getter, not observable, so follow range changes.
      const selection = editor.model.document.selection;
      const update = () => { button.isEnabled = !selection.isCollapsed; };
      this.listenTo(selection, 'change:range', update);
      update();
      this.listenTo(button, 'execute', () => this.openDialog());
      return button;
    });
  }

  openDialog() {
    const selection = this.readSelection();
    if (selection.blocks > 1) {
      Notification.warning('Fact', 'Select words within one paragraph.');
      return;
    }
    const contentUid = this.contentUid();
    if (contentUid === null) {
      Notification.warning('Fact', 'Save the content element first, then add facts.');
      return;
    }

    const form = this.buildForm(selection);
    const modal = Modal.advanced({
      title: 'New fact',
      content: form,
      severity: Severity.notice,
      buttons: [
        { text: 'Cancel', btnClass: 'btn-default', trigger: () => modal.hideModal() },
        { text: 'Create fact', btnClass: 'btn-primary', name: 'create', trigger: () => this.submit(form, contentUid, selection.text, modal) },
      ],
    });
    this.attachLookups(form, selection.text);
  }

  /**
   * Other extensions can help editors fill the form: each module listed in the
   * editor configuration "provenanceFact.lookups" exports a default function
   * that receives the form, the quote, an empty panel above the fields and
   * fill(values). A lookup that fails to load leaves the dialog as it is.
   */
  async attachLookups(form, quote) {
    const panel = form.querySelector('.provenance-fact-lookups');
    const fill = (values) => {
      for (const [name, value] of Object.entries(values)) {
        const input = form.querySelector(`[name="${name}"]`);
        if (input && typeof value === 'string' && input.value !== value) {
          input.value = value;
          input.classList.add('is-valid');
        }
      }
    };
    for (const specifier of this.editor.config.get('provenanceFact.lookups') ?? []) {
      try {
        const module = await import(specifier);
        await module.default({ form, quote, panel, fill });
      } catch (error) {
        console.warn(`Fact lookup ${specifier} could not be loaded.`, error);
      }
    }
  }

  readSelection() {
    const selection = this.editor.model.document.selection;
    let text = '';
    let formatted = false;
    for (const range of selection.getRanges()) {
      for (const item of range.getItems()) {
        if (item.is('$textProxy')) {
          text += item.data;
          formatted = formatted || Array.from(item.getAttributeKeys()).length > 0;
        }
      }
    }
    return { text: text.trim(), formatted, blocks: Array.from(selection.getSelectedBlocks()).length };
  }

  contentUid() {
    // TYPO3 renders the editor in the shadow DOM of <typo3-rte-ckeditor-ckeditor5>;
    // the field's textarea sits in the host's light DOM.
    const element = this.editor.ui.view.element;
    const root = element?.getRootNode();
    const host = root instanceof ShadowRoot ? root.host : element?.closest('typo3-rte-ckeditor-ckeditor5');
    const textarea = host?.querySelector('textarea[slot="textarea"]');
    const match = textarea ? FIELD_NAME.exec(textarea.name) : null;
    return match ? Number(match[1]) : null;
  }

  buildForm(selection) {
    const form = document.createElement('form');
    form.className = 'provenance-fact-form';
    form.addEventListener('submit', (event) => event.preventDefault());

    const quote = document.createElement('blockquote');
    quote.className = 'blockquote';
    quote.textContent = selection.text;
    form.append(quote);
    if (selection.formatted) {
      form.append(this.hint('The selection contains formatting or a link. It stays citable, but the page cannot mark it.'));
    }
    const lookups = document.createElement('div');
    lookups.className = 'provenance-fact-lookups';
    form.append(lookups);

    form.append(
      this.field('factId', 'Fact ID', 'input', this.slug(selection.text), 'Lowercase, unique, for example employees-2025. Becomes the anchor #fact-…'),
      this.field('subject', 'Subject (URI)', 'input', '', 'What the fact is about, for example https://example.org/about'),
      this.field('predicate', 'Predicate', 'input', '', 'A schema.org term such as numberOfEmployees, or a URI'),
      this.field('objectLiteral', 'Value', 'input', selection.text, ''),
      this.field('objectUri', 'Value as URI', 'input', '', 'Optional, instead of the value'),
      this.field('sourceUrl', 'Evidence (URL)', 'input', '', 'Optional: the document that proves the fact'),
      this.select('confidence', 'Confidence', [['asserted', 'Asserted'], ['estimated', 'Estimated'], ['measured', 'Measured']]),
    );
    return form;
  }

  field(name, label, type, value, help) {
    const wrapper = document.createElement('div');
    wrapper.className = 'form-group mb-2';
    const labelElement = document.createElement('label');
    labelElement.className = 'form-label';
    labelElement.textContent = label;
    const input = document.createElement(type);
    input.className = 'form-control';
    input.name = name;
    input.value = value;
    labelElement.htmlFor = input.id = 'provenance-fact-' + name;
    wrapper.append(labelElement, input);
    if (help) {
      const helpText = document.createElement('div');
      helpText.className = 'form-text';
      helpText.textContent = help;
      wrapper.append(helpText);
    }
    return wrapper;
  }

  select(name, label, options) {
    const wrapper = this.field(name, label, 'select', '', '');
    const select = wrapper.querySelector('select');
    select.className = 'form-select';
    for (const [value, text] of options) {
      select.append(new Option(text, value));
    }
    return wrapper;
  }

  hint(text) {
    const hint = document.createElement('div');
    hint.className = 'alert alert-warning';
    hint.textContent = text;
    return hint;
  }

  slug(text) {
    return text.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 48);
  }

  async submit(form, contentUid, quote, modal) {
    form.querySelectorAll('.is-invalid').forEach((element) => element.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach((element) => element.remove());
    const data = Object.fromEntries(new FormData(form).entries());
    try {
      const response = await new AjaxRequest(TYPO3.settings.ajaxUrls.provenance_facts_create)
        .post({ ...data, contentUid, quote });
      const result = await response.resolve();
      modal.hideModal();
      Notification.success('Fact created', `"${result.factId}" will be marked on the page. Reload the form to see it under "Provenance facts".`);
    } catch (error) {
      const result = error.response ? await error.response.json().catch(() => ({})) : {};
      const errors = result.errors ?? { general: 'The fact could not be saved.' };
      for (const [name, message] of Object.entries(errors)) {
        const input = form.querySelector(`[name="${name}"]`);
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback d-block';
        feedback.textContent = message;
        if (input) {
          input.classList.add('is-invalid');
          input.after(feedback);
        } else {
          form.prepend(feedback);
        }
      }
    }
  }
}

export default ProvenanceFact;
