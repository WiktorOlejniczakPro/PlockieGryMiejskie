package pl.plock.pgm.location

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.Bundle
import android.os.Looper
import android.util.Log
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/** Pozycja gracza. accuracyM – dokładność w metrach (im mniej, tym lepiej). */
data class PlayerLocation(
    val latitude: Double,
    val longitude: Double,
    val accuracyM: Float,
    val simulated: Boolean = false,
    /** Kiedy zmierzono pozycję (System.currentTimeMillis). */
    val timeMs: Long = System.currentTimeMillis(),
) {
    /** Pomiar z ostatnich [maxAgeMs] ms (pozycja ustawiona w trybie demo jest zawsze aktualna). */
    fun isFresh(maxAgeMs: Long = 30_000): Boolean = simulated || System.currentTimeMillis() - timeMs <= maxAgeMs
}

/**
 * Śledzenie pozycji GPS (wbudowany LocationManager Androida – bez usług Google).
 * Korzysta z GPS i z sieci. Pozycja z GPS ma pierwszeństwo – pozycja z sieci (Wi-Fi/BTS,
 * zwykle kilkadziesiąt–kilkaset metrów błędu) jest używana tylko, gdy GPS jeszcze milczy.
 * Stare „ostatnie znane” pozycje (sprzed > 2 min) są pomijane.
 */
class LocationTracker(context: Context) {

    private val appContext = context.applicationContext
    private val manager = appContext.getSystemService(Context.LOCATION_SERVICE) as LocationManager
    private val _location = MutableStateFlow<PlayerLocation?>(null)
    val location: StateFlow<PlayerLocation?> = _location.asStateFlow()

    private var running = false
    private var lastGpsAt = 0L

    // Pełna implementacja (nie lambda): na Androidzie 8–10 metody onStatusChanged /
    // onProviderEnabled / onProviderDisabled są abstrakcyjne i ich brak wywracał aplikację.
    private val listener = object : LocationListener {
        override fun onLocationChanged(location: Location) = update(location, live = true)
        @Deprecated("Wymagane na Androidzie < 10")
        override fun onStatusChanged(provider: String?, status: Int, extras: Bundle?) {}
        override fun onProviderEnabled(provider: String) {}
        override fun onProviderDisabled(provider: String) {}
    }

    fun hasPermission(): Boolean =
        appContext.checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED ||
            appContext.checkSelfPermission(Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED

    @SuppressLint("MissingPermission")
    fun start() {
        if (running || !hasPermission()) return
        running = true
        for (provider in listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)) {
            try {
                if (!manager.allProviders.contains(provider)) continue
                manager.getLastKnownLocation(provider)
                    ?.takeIf { System.currentTimeMillis() - it.time < 2 * 60_000 }
                    ?.let { update(it, live = false) }
                manager.requestLocationUpdates(provider, 1000L, 0f, listener, Looper.getMainLooper())
            } catch (e: Exception) {
                // np. tylko „przybliżona” lokalizacja (brak zgody na GPS) albo brak dostawcy –
                // aplikacja działa dalej z tym, co jest dostępne
                Log.w("PGM", "Dostawca lokalizacji $provider niedostępny: ${e.message}")
            }
        }
    }

    fun stop() {
        if (!running) return
        running = false
        try {
            manager.removeUpdates(listener)
        } catch (e: Exception) {
            Log.w("PGM", "removeUpdates: ${e.message}")
        }
    }

    fun isGpsEnabled(): Boolean = try {
        manager.isProviderEnabled(LocationManager.GPS_PROVIDER) || manager.isProviderEnabled(LocationManager.NETWORK_PROVIDER)
    } catch (e: Exception) {
        false
    }

    private fun update(location: Location, live: Boolean) {
        val now = System.currentTimeMillis()
        val accuracy = if (location.hasAccuracy()) location.accuracy else 50f
        val fromGps = location.provider == LocationManager.GPS_PROVIDER
        // dopóki GPS podaje pozycję (ostatnie 15 s), pozycja z sieci jej nie zastępuje
        if (!fromGps && now - lastGpsAt < 15_000) return
        if (fromGps && live) lastGpsAt = now
        _location.value = PlayerLocation(
            location.latitude, location.longitude, accuracy,
            // świeży pomiar = teraz (zegar GPS bywa przesunięty); ostatnia znana = jej czas
            timeMs = if (live) now else minOf(location.time, now),
        )
    }
}
