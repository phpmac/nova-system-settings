let mix = require('laravel-mix')
let NovaExtension = require('laravel-nova-devtool')

mix.extend('nova', new NovaExtension())

mix
  .setPublicPath('dist')
  .js('resources/js/tool.js', 'js')
  .vue({ version: 3 })
  .css('resources/css/tool.css', 'css')
  .nova('vcsb-system-settings')
  .version()

// 异步块并入主包: 拆出去的 chunk 不在 mix-manifest 里会被 Nova 判 404 (盈利日历同款)
mix.override((config) => {
  config.output.asyncChunks = false
})

mix.disableNotifications()
