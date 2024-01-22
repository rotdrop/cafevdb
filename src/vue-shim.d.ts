declare module '@nextcloud/vue/dist/Directives/*.js' {
  import type { DirectiveOptions } from 'vue';

  const DirectiveVue: DirectiveOptions<>;

  export default DirectiveVue;
}

declare module '@nextcloud/vue/dist/Components/*.js' {
  import Vue from 'vue';
  export default Vue;
}
