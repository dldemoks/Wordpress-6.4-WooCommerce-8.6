const settingsPayeer = window.wc.wcSettings.getSetting('payeer_data', {});
const labelPayeer = window.wp.htmlEntities.decodeEntities(settingsPayeer.title) || window.wp.i18n.__('Payeer', 'wc-payeer');
const ContentPayeer = () => {
	return window.wp.htmlEntities.decodeEntities(settingsPayeer.description || '');
};
const Payeer_Gateway = {
	name: 'payeer',
	label: labelPayeer,
	content: Object(window.wp.element.createElement)(ContentPayeer, null),
	edit: Object(window.wp.element.createElement)(ContentPayeer, null),
	canMakePayment: () => true,
	ariaLabel: labelPayeer,
	supports: {
		features: settingsPayeer.supports,
	},
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Payeer_Gateway);