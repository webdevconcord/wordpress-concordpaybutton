/**
 * ConcordPay Button Block
 *
 * @package 'concordpay-button'
 */
(function (blocks, editor, i18n, element) {
	const el = element.createElement;
	const __ = i18n.__;

	const RichText = editor.RichText;

	blocks.registerBlockType(
		'concordpay-button/cpb-block',
		{
			title: __('ConcordPay Button', 'concordpay-button'),
			icon: 'shortcode',
			category: 'text',
			keywords: ['concordpay', 'cpb', 'payment', 'button'],

			attributes: {
				name: {
					type: 'string',
					default: __('Example name', 'concordpay-button')
				},
				price: {
					type: 'string',
					default: '0.00'
				},
				size: {
					type: 'string',
					source: 'children',
					selector: 'p'
				},
				align: {
					type: 'string',
					source: 'children',
					selector: 'p'
				}
			},

			edit: props => {
				const {
					attributes: {name, price, size, align}
				} = props;

				function onChangeName(event) {
					props.setAttributes({name: event.target.value});
				}

				function onChangePrice(event) {
					props.setAttributes({price: event.target.value});
				}

				return (
					el(
						'div',
						{class: 'js-cpb-wrapper'},
						el('span', {class: 'js-cpb-label'}, __('Please specify Product name and Price', 'concordpay-button')),
						el(
							'div',
							{class: 'js-cpb-container'},
							el('input', {class: 'js-cpb-input', value: name, onChange: onChangeName}),
							el('input', {class: 'js-cpb-input', value: price, onChange: onChangePrice})
						)
					)
				);
			},

			save: props => {
				const shortcode = "[cpb name='" + props.attributes.name + "' price='" + props.attributes.price + "']";
				return el('p', {}, shortcode);
			}
		}
	);

})(
	window.wp.blocks,
	window.wp.editor,
	window.wp.i18n,
	window.wp.element
);
