const Vue = require( 'vue' );
const App = require( './App.vue' );

const mountPoint = document.getElementById( 'namespacemanager-app' );
if ( mountPoint ) {
	Vue.createMwApp( App ).mount( mountPoint );
}

module.exports = App;
