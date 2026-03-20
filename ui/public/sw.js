const CACHE_NAME = 'orkestr-v1'
const APP_SHELL = [
  '/',
  '/index.html',
  '/manifest.json',
  '/logo.png',
]

// Install — cache app shell
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(APP_SHELL).catch(() => {
        // Some assets may not exist yet; continue gracefully
      })
    })
  )
  self.skipWaiting()
})

// Activate — clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys
          .filter((key) => key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    })
  )
  self.clients.claim()
})

// Fetch — network-first for API, cache-first for static assets
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url)

  // API calls: network-first
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          // Cache successful GET responses
          if (event.request.method === 'GET' && response.ok) {
            const clone = response.clone()
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, clone)
            })
          }
          return response
        })
        .catch(() => {
          // Fall back to cache for GET requests when offline
          if (event.request.method === 'GET') {
            return caches.match(event.request)
          }
          return new Response(JSON.stringify({ error: 'Offline' }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' },
          })
        })
    )
    return
  }

  // Static assets: cache-first
  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) {
        // Refresh cache in background
        fetch(event.request)
          .then((response) => {
            if (response.ok) {
              caches.open(CACHE_NAME).then((cache) => {
                cache.put(event.request, response)
              })
            }
          })
          .catch(() => {})
        return cached
      }

      return fetch(event.request)
        .then((response) => {
          if (response.ok && event.request.method === 'GET') {
            const clone = response.clone()
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(event.request, clone)
            })
          }
          return response
        })
        .catch(() => {
          // For navigation requests, return the cached index.html (SPA)
          if (event.request.mode === 'navigate') {
            return caches.match('/index.html')
          }
          return new Response('', { status: 404 })
        })
    })
  )
})

// Push notifications
self.addEventListener('push', (event) => {
  let data = { title: 'Orkestr', body: 'New notification' }

  if (event.data) {
    try {
      data = event.data.json()
    } catch {
      data = { title: 'Orkestr', body: event.data.text() }
    }
  }

  const options = {
    body: data.body || '',
    icon: data.icon || '/logo.png',
    badge: data.badge || '/logo.png',
    data: data.data || {},
    vibrate: [200, 100, 200],
    actions: [],
    tag: data.data?.tag || 'orkestr-notification',
    renotify: true,
  }

  // Add approve/reject actions for approval notifications
  if (data.data?.type === 'approval') {
    options.actions = [
      { action: 'approve', title: 'Approve' },
      { action: 'reject', title: 'Reject' },
    ]
  }

  event.waitUntil(
    self.registration.showNotification(data.title || 'Orkestr', options)
  )
})

// Notification click handler
self.addEventListener('notificationclick', (event) => {
  event.notification.close()

  const url = event.notification.data?.url || '/dashboard'

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      // Focus existing window if available
      for (const client of clients) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          client.navigate(url)
          return client.focus()
        }
      }
      // Open new window
      return self.clients.openWindow(url)
    })
  )
})
