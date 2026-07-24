import { config as defaultConfig } from 'wdio-mediawiki/wdio-defaults.conf.js';

export const config = { ...defaultConfig,
	// Both specs recreate Cargo tables, which cannot run at the same time.
	maxInstances: 1
};
