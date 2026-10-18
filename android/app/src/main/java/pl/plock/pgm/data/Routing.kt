package pl.plock.pgm.data

import com.google.gson.Gson
import okhttp3.OkHttpClient
import pl.plock.pgm.Config
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import retrofit2.http.GET
import retrofit2.http.Path
import retrofit2.http.Query
import java.util.concurrent.TimeUnit

/**
 * Wyznaczanie trasy po drogach, chodnikach i ścieżkach rowerowych.
 *
 * Darmowy serwer OSRM na danych OpenStreetMap (routing.openstreetmap.de, bez klucza API).
 * Profil „pieszo” prowadzi chodnikami, deptakami i ścieżkami; „rowerem” – drogami
 * i drogami rowerowymi. Serwer jest publiczny i ma limit „rozsądnego użycia”:
 * na produkcję warto postawić własny OSRM / GraphHopper i zmienić Config.ROUTING_BASE_URL.
 */
enum class RouteMode(val profile: String, val label: String) {
    FOOT("routed-foot", "Pieszo"),
    BIKE("routed-bike", "Rowerem"),
}

/** Wyznaczona trasa: punkty linii (lat, lng), długość w metrach i szacowany czas w sekundach. */
data class NavRoute(
    val points: List<Pair<Double, Double>>,
    val distanceM: Double,
    val durationS: Double,
    val mode: RouteMode,
    val targetLat: Double,
    val targetLng: Double,
    val targetName: String,
    /** true = linia prosta (serwer tras nie odpowiedział) – aplikacja spróbuje ponownie */
    val approximate: Boolean = false,
)

/** Zastępcza „trasa” w linii prostej, gdy serwer tras jest niedostępny (czas: marsz ok. 4,5 km/h). */
fun straightRoute(mode: RouteMode, fromLat: Double, fromLng: Double, toLat: Double, toLng: Double, targetName: String): NavRoute {
    val r = 6371008.8
    val dLat = Math.toRadians(toLat - fromLat)
    val dLng = Math.toRadians(toLng - fromLng)
    val a = Math.sin(dLat / 2).let { it * it } +
        Math.cos(Math.toRadians(fromLat)) * Math.cos(Math.toRadians(toLat)) * Math.sin(dLng / 2).let { it * it }
    val distance = 2 * r * Math.asin(Math.sqrt(a).coerceAtMost(1.0))
    val speed = if (mode == RouteMode.BIKE) 4.2 else 1.25
    return NavRoute(listOf(fromLat to fromLng, toLat to toLng), distance, distance / speed, mode, toLat, toLng, targetName, approximate = true)
}

interface RoutingApi {
    /** OSRM: współrzędne w kolejności długość,szerokość; „driving” to stała część adresu tego serwera. */
    @GET("{profile}/route/v1/driving/{coords}")
    suspend fun route(
        @Path("profile") profile: String,
        @Path(value = "coords", encoded = true) coords: String,
        @Query("overview") overview: String = "full",
        @Query("geometries") geometries: String = "geojson",
        @Query("steps") steps: Boolean = false,
    ): OsrmResponse
}

data class OsrmResponse(
    val code: String?,
    val message: String?,
    val routes: List<OsrmRoute>?,
)

data class OsrmRoute(
    val distance: Double,
    val duration: Double,
    val geometry: OsrmGeometry?,
)

/** GeoJSON LineString: coordinates = [[lng, lat], …]. */
data class OsrmGeometry(val coordinates: List<List<Double>>?)

object RoutingClient {
    val api: RoutingApi by lazy {
        val http = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(20, TimeUnit.SECONDS)
            .addInterceptor { chain ->
                // zasady serwera: przedstaw się aplikacją
                chain.proceed(chain.request().newBuilder().header("User-Agent", "PGM-PlockieGryMiejskie/1.0 (Android)").build())
            }
            .build()
        Retrofit.Builder()
            .baseUrl(Config.ROUTING_BASE_URL)
            .client(http)
            .addConverterFactory(GsonConverterFactory.create(Gson()))
            .build()
            .create(RoutingApi::class.java)
    }

    /** Trasa z (fromLat, fromLng) do celu; wyjątek, gdy serwer nie znalazł drogi. */
    suspend fun route(
        mode: RouteMode,
        fromLat: Double, fromLng: Double,
        toLat: Double, toLng: Double,
        targetName: String,
    ): NavRoute {
        val coords = "%.6f,%.6f;%.6f,%.6f".format(java.util.Locale.US, fromLng, fromLat, toLng, toLat)
        return api.route(mode.profile, coords).toNavRoute(mode, toLat, toLng, targetName)
    }
}

fun OsrmResponse.toNavRoute(mode: RouteMode, toLat: Double, toLng: Double, targetName: String): NavRoute {
    val best = routes?.firstOrNull()
    val line = best?.geometry?.coordinates?.mapNotNull { if (it.size >= 2) it[1] to it[0] else null }
    if (code != "Ok" || best == null || line.isNullOrEmpty()) {
        throw ApiError("Nie udało się wyznaczyć trasy do tego miejsca.", 0)
    }
    // linia kończy się na najbliższej drodze – dociągamy ją do samego punktu
    val points = if (line.last() != (toLat to toLng)) line + (toLat to toLng) else line
    return NavRoute(points, best.distance, best.duration, mode, toLat, toLng, targetName)
}

/** Odległość (m) od pozycji do najbliższego odcinka trasy (rzut płaski – wystarczy w skali miasta). */
fun NavRoute.distanceFrom(lat: Double, lng: Double): Double {
    if (points.isEmpty()) return Double.MAX_VALUE
    val mPerLat = 111_320.0
    val mPerLng = 111_320.0 * Math.cos(Math.toRadians(lat))
    fun xy(p: Pair<Double, Double>) = (p.second - lng) * mPerLng to (p.first - lat) * mPerLat
    var best = Double.MAX_VALUE
    var prev = xy(points.first())
    if (points.size == 1) return Math.hypot(prev.first, prev.second)
    for (i in 1 until points.size) {
        val cur = xy(points[i])
        val dx = cur.first - prev.first
        val dy = cur.second - prev.second
        val len2 = dx * dx + dy * dy
        // rzut punktu (0,0) na odcinek prev–cur
        val t = if (len2 == 0.0) 0.0 else (-(prev.first * dx + prev.second * dy) / len2).coerceIn(0.0, 1.0)
        val d = Math.hypot(prev.first + t * dx, prev.second + t * dy)
        if (d < best) best = d
        prev = cur
    }
    return best
}
