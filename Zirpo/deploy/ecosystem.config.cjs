module.exports = {
  apps: [{
    name: 'zirpo-webhook',
    script: 'webhook.js',
    cwd: '/mnt/m2/www/default/Zirpo/deploy',
    env: {
      WEBHOOK_PORT: 9000,
      WEBHOOK_SECRET: '',
      PROJECT_DIR: '/mnt/m2/www/default/Zirpo'
    }
  }]
}
