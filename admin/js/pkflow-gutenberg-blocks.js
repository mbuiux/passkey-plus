(function (wp) {
    if (!wp || !wp.blocks || !wp.element || !wp.i18n || !wp.blockEditor || !wp.components) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var createElement = wp.element.createElement;
    var __ = wp.i18n.__;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var ToggleControl = wp.components.ToggleControl;
    var SelectControl = wp.components.SelectControl;

    var registry = (window.PKFLOWIntegrationBlocks && Array.isArray(window.PKFLOWIntegrationBlocks.blocks))
        ? window.PKFLOWIntegrationBlocks.blocks
        : [];

    if (!registry.length) {
        return;
    }

    var renderFieldControl = function (props, field) {
        if (!field || !field.name) {
            return null;
        }

        var value = props.attributes[field.name];

        if ('textarea' === field.type) {
            return createElement(TextareaControl, {
                key: field.name,
                label: field.label || field.name,
                help: field.help || undefined,
                value: value || '',
                placeholder: field.placeholder || '',
                onChange: function (newValue) {
                    var update = {};
                    update[field.name] = newValue;
                    props.setAttributes(update);
                }
            });
        }

        if ('toggle' === field.type) {
            return createElement(ToggleControl, {
                key: field.name,
                label: field.label || field.name,
                help: field.help || undefined,
                checked: !!value,
                onChange: function (newValue) {
                    var update = {};
                    update[field.name] = !!newValue;
                    props.setAttributes(update);
                }
            });
        }

        if ('select' === field.type) {
            return createElement(SelectControl, {
                key: field.name,
                label: field.label || field.name,
                help: field.help || undefined,
                value: value || '',
                options: Array.isArray(field.options) ? field.options : [],
                onChange: function (newValue) {
                    var update = {};
                    update[field.name] = newValue;
                    props.setAttributes(update);
                }
            });
        }

        return createElement(TextControl, {
            key: field.name,
            type: 'url' === field.type ? 'url' : 'text',
            label: field.label || field.name,
            help: field.help || undefined,
            value: value || '',
            placeholder: field.placeholder || '',
            onChange: function (newValue) {
                var update = {};
                update[field.name] = newValue;
                props.setAttributes(update);
            }
        });
    };

    registry.forEach(function (blockConfig) {
        if (!blockConfig || !blockConfig.name) {
            return;
        }

        var fieldList = Array.isArray(blockConfig.inspector_fields) ? blockConfig.inspector_fields : [];
        var attributes = blockConfig.attributes && 'object' === typeof blockConfig.attributes
            ? blockConfig.attributes
            : {
                label: {
                    type: 'string',
                    default: blockConfig.label || __('Sign in with Passkey', 'passkeyflow')
                }
            };

        registerBlockType(blockConfig.name, {
            title: blockConfig.title || __('Passkey Login', 'passkeyflow'),
            description: blockConfig.description || __('Render a passkey sign-in control.', 'passkeyflow'),
            icon: blockConfig.icon || 'shield',
            category: blockConfig.category || 'widgets',
            keywords: Array.isArray(blockConfig.keywords) ? blockConfig.keywords : ['passkey', 'login'],
            attributes: attributes,
            edit: function (props) {
                var buttonLabel = props.attributes.button_label || props.attributes.label || blockConfig.label || __('Sign in with Passkey', 'passkeyflow');
                var title = props.attributes.title || blockConfig.title || __('Passkey Login', 'passkeyflow');
                var hint = blockConfig.description || __('Preview of the passkey CTA shown for this block.', 'passkeyflow');

                return createElement(
                    'div',
                    { className: 'pkflow-block-editor-card' },
                    createElement(
                        InspectorControls,
                        null,
                        createElement(
                            PanelBody,
                            {
                                title: __('Block settings', 'passkeyflow'),
                                initialOpen: true
                            },
                            fieldList.map(function (field) {
                                return renderFieldControl(props, field);
                            })
                        )
                    ),
                    createElement('p', { className: 'pkflow-block-editor-card__title' }, title),
                    createElement('p', { className: 'pkflow-block-editor-card__hint' }, hint),
                    createElement('button', {
                        type: 'button',
                        className: 'button button-primary',
                        disabled: true
                    }, buttonLabel)
                );
            },
            save: function () {
                return null;
            }
        });
    });
})(window.wp);
