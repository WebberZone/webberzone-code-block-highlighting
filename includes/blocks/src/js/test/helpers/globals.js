const { TextEncoder, TextDecoder } = require('util');

// jsdom does not provide these; @wordpress/block-editor needs them at import.
global.TextEncoder = global.TextEncoder || TextEncoder;
global.TextDecoder = global.TextDecoder || TextDecoder;
