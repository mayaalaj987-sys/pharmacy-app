# Google Maps Android setup

The repository intentionally contains no usable Google API key. The committed
`local.defaults.properties` value only keeps tests and non-map builds
configurable after cloning.

For local development, create `android/secrets.properties` (it is ignored by
Git) with:

```properties
MAPS_API_KEY=your_restricted_development_key
```

Google Maps rendering requires a Google Cloud project with billing enabled and
Maps SDK for Android enabled. Use separate development and production keys.
Restrict each key to the Android application ID and the SHA-1 certificate for
the corresponding signing key, then apply the API restriction **Maps SDK for
Android only**. Never use an unrestricted server credential in this app.

This milestone deliberately retains the placeholder application ID and debug
release signing. Update production identity and signing in a separate release
hardening task before creating the production key restriction.
