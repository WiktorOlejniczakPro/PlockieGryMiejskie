package pl.plock.pgm.ui

import android.content.Context
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.graphics.drawable.BitmapDrawable
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clipToBounds
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.graphics.PathParser
import org.osmdroid.events.MapEventsReceiver
import org.osmdroid.tileprovider.tilesource.TileSourceFactory
import org.osmdroid.util.BoundingBox
import org.osmdroid.util.GeoPoint
import org.osmdroid.views.CustomZoomButtonsController
import org.osmdroid.views.MapView
import org.osmdroid.views.overlay.MapEventsOverlay
import org.osmdroid.views.overlay.Marker
import org.osmdroid.views.overlay.Polygon
import org.osmdroid.views.overlay.Polyline
import pl.plock.pgm.Config
import pl.plock.pgm.location.PlayerLocation

/**
 * Punkt na mapie. Punkty trasy mają numer, miejsca z „Odkrywaj” – ikonę ([icon] = ścieżka z PgmIcons).
 */
data class MapPoint(
    val id: Int,
    val number: Int,
    val latitude: Double,
    val longitude: Double,
    val state: PointState,
    val icon: String? = null,
)

enum class PointState { TODO, TARGET, DONE, PLACE, PLACE_SELECTED }

/** Prośba o przesunięcie mapy; nowy [key] = nowe przesunięcie (np. po kliknięciu „Pokaż mnie”). */
data class MapFocus(
    val key: Int,
    val latitude: Double,
    val longitude: Double,
    val zoom: Double? = null,
    /** Gdy podane – mapa dopasowuje widok tak, by zmieściły się wszystkie te punkty (lat, lng). */
    val fit: List<Pair<Double, Double>>? = null,
)

/**
 * Mapa OpenStreetMap (osmdroid): ponumerowane punkty, linia trasy
 * (połączenie schematyczne), trasa prowadzenia po drogach ([navRoute]),
 * pozycja gracza i promień docelowego punktu. Przytrzymanie palca – onLongPress.
 *
 * @param drawRoute   łączyć punkty linią w kolejności numerów (trasa gry)
 * @param fitToPoints przy pierwszym wyświetleniu dopasować widok do wszystkich punktów
 */
@Composable
fun OsmMap(
    points: List<MapPoint>,
    modifier: Modifier = Modifier,
    player: PlayerLocation? = null,
    targetRadiusM: Int? = null,
    focus: MapFocus? = null,
    drawRoute: Boolean = true,
    fitToPoints: Boolean = true,
    onPointClick: (Int) -> Unit = {},
    onLongPress: ((Double, Double) -> Unit)? = null,
    interactive: Boolean = true,
    /** Trasa prowadzenia po drogach (lat, lng) – rysowana grubą linią nad resztą. */
    navRoute: List<Pair<Double, Double>>? = null,
) {
    // stan mapy przetrwa przerysowania (nie jest stanem Compose – nie wywołuje rekompozycji)
    val memory = remember { MapMemory() }

    AndroidView(
        // clipToBounds: kafelki mapy nie wychodzą poza jej obszar przy przesuwaniu
        modifier = modifier.clipToBounds(),
        factory = { context ->
            MapView(context).apply {
                setTileSource(TileSourceFactory.MAPNIK)
                setMultiTouchControls(interactive)
                isClickable = interactive
                zoomController.setVisibility(CustomZoomButtonsController.Visibility.NEVER)
                minZoomLevel = 11.0
                maxZoomLevel = 19.0
                // mapa nie „powtarza się” i nie da się jej odsunąć daleko od okolic Płocka
                isHorizontalMapRepetitionEnabled = false
                isVerticalMapRepetitionEnabled = false
                setScrollableAreaLimitDouble(BoundingBox(52.75, 20.05, 52.35, 19.35))
                controller.setZoom(16.0)
                controller.setCenter(GeoPoint(Config.PLOCK_LAT, Config.PLOCK_LNG))
                onResume()
                memory.map = this
            }
        },
        update = { map ->
            map.overlays.clear()

            map.overlays.add(MapEventsOverlay(object : MapEventsReceiver {
                override fun singleTapConfirmedHelper(p: GeoPoint): Boolean = false
                override fun longPressHelper(p: GeoPoint): Boolean {
                    val handler = onLongPress ?: return false
                    handler(p.latitude, p.longitude)
                    return true
                }
            }))

            val routePoints = points.filter { it.state != PointState.PLACE && it.state != PointState.PLACE_SELECTED }
                .sortedBy { it.number }
                .map { GeoPoint(it.latitude, it.longitude) }
            if (drawRoute && routePoints.size > 1) {
                map.overlays.add(Polyline(map).apply {
                    setPoints(routePoints)
                    outlinePaint.color = Color.parseColor("#1E6FD9")
                    outlinePaint.strokeWidth = 8f
                    outlinePaint.alpha = if (navRoute != null) 90 else 170
                })
            }

            if (navRoute != null && navRoute.size > 1) {
                val line = navRoute.map { GeoPoint(it.first, it.second) }
                // biała obwódka + zielona linia – dobrze widać na każdym tle
                map.overlays.add(Polyline(map).apply {
                    setPoints(line)
                    outlinePaint.color = Color.WHITE
                    outlinePaint.strokeWidth = dp(map.context, 9f)
                    outlinePaint.strokeCap = Paint.Cap.ROUND
                    outlinePaint.strokeJoin = Paint.Join.ROUND
                    setOnClickListener { _, _, _ -> false }
                })
                map.overlays.add(Polyline(map).apply {
                    setPoints(line)
                    outlinePaint.color = Color.parseColor("#12A150")
                    outlinePaint.strokeWidth = dp(map.context, 5.5f)
                    outlinePaint.strokeCap = Paint.Cap.ROUND
                    outlinePaint.strokeJoin = Paint.Join.ROUND
                    setOnClickListener { _, _, _ -> false }
                })
            }

            val target = points.firstOrNull { it.state == PointState.TARGET }
            if (target != null && targetRadiusM != null) {
                map.overlays.add(Polygon(map).apply {
                    setPoints(Polygon.pointsAsCircle(GeoPoint(target.latitude, target.longitude), targetRadiusM.toDouble()))
                    fillPaint.color = Color.argb(40, 30, 111, 217)
                    outlinePaint.color = Color.argb(140, 30, 111, 217)
                    outlinePaint.strokeWidth = 3f
                    setOnClickListener { _, _, _ -> false }
                })
            }

            // zaznaczony punkt rysujemy na końcu – będzie na wierzchu
            for (point in points.sortedBy { it.state == PointState.PLACE_SELECTED || it.state == PointState.TARGET }) {
                map.overlays.add(Marker(map).apply {
                    position = GeoPoint(point.latitude, point.longitude)
                    icon = BitmapDrawable(map.resources, memory.icon(map.context, point))
                    setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
                    setInfoWindow(null)
                    setOnMarkerClickListener { _, _ ->
                        onPointClick(point.id)
                        true
                    }
                })
            }

            if (player != null) {
                map.overlays.add(Marker(map).apply {
                    position = GeoPoint(player.latitude, player.longitude)
                    icon = BitmapDrawable(map.resources, memory.playerIcon(map.context, player.simulated))
                    setAnchor(Marker.ANCHOR_CENTER, Marker.ANCHOR_CENTER)
                    setInfoWindow(null)
                    setOnMarkerClickListener { _, _ -> true }
                })
            }

            if (focus != null && focus.key != memory.focusKey) {
                memory.focusKey = focus.key
                memory.centered = true
                val fit = focus.fit
                if (fit != null && fit.size > 1) {
                    val box = BoundingBox.fromGeoPointsSafe(fit.map { GeoPoint(it.first, it.second) })
                    map.post {
                        try {
                            map.zoomToBoundingBox(box.increaseByScale(1.3f), true)
                        } catch (e: Exception) {
                            map.controller.animateTo(GeoPoint(focus.latitude, focus.longitude))
                        }
                    }
                } else {
                    focus.zoom?.let { map.controller.setZoom(it) }
                    map.controller.animateTo(GeoPoint(focus.latitude, focus.longitude))
                }
            } else if (!memory.centered && points.isNotEmpty() && fitToPoints) {
                memory.centered = true
                val all = points.map { GeoPoint(it.latitude, it.longitude) }
                if (all.size == 1) {
                    map.controller.setCenter(all.first())
                } else {
                    val box = BoundingBox.fromGeoPointsSafe(all)
                    map.post {
                        try {
                            map.zoomToBoundingBox(box.increaseByScale(1.4f), false)
                        } catch (e: Exception) {
                            map.controller.setCenter(all.first())
                        }
                    }
                }
            }
            map.invalidate()
        },
    )

    DisposableEffect(Unit) {
        onDispose {
            memory.map?.onDetach()
            memory.map = null
        }
    }
}

/** Pamięć mapy: wycentrowanie, ostatnie przesunięcie i gotowe ikony (rysowane raz). */
private class MapMemory {
    var map: MapView? = null
    var centered = false
    var focusKey = Int.MIN_VALUE
    private val icons = HashMap<String, Bitmap>()

    fun icon(context: Context, point: MapPoint): Bitmap {
        val key = "${point.state}|${point.icon ?: point.number}"
        return icons.getOrPut(key) { pointBitmap(context, point) }
    }

    fun playerIcon(context: Context, simulated: Boolean): Bitmap =
        icons.getOrPut("player|$simulated") { playerBitmap(context, simulated) }
}

private fun dp(context: Context, value: Float): Float = value * context.resources.displayMetrics.density

/**
 * Kółko z numerem (punkty trasy: niebieskie – cel, zielone – ukończony, granatowe – pozostałe)
 * albo z ikoną (miejsca: białe z granatową ikoną, zaznaczone – niebieskie z białą).
 */
private fun pointBitmap(context: Context, point: MapPoint): Bitmap {
    val big = point.state == PointState.TARGET || point.state == PointState.PLACE_SELECTED
    val size = dp(context, if (big) 38f else if (point.state == PointState.PLACE) 28f else 30f).toInt()
    val bitmap = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888)
    val canvas = Canvas(bitmap)
    val fill = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = when (point.state) {
            PointState.TARGET, PointState.PLACE_SELECTED -> Color.parseColor("#1E6FD9")
            PointState.DONE -> Color.parseColor("#2E9E5B")
            PointState.TODO -> Color.parseColor("#0B2A4A")
            PointState.PLACE -> Color.WHITE
        }
    }
    val border = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = if (point.state == PointState.PLACE) Color.parseColor("#0B2A4A") else Color.WHITE
        style = Paint.Style.STROKE
        strokeWidth = dp(context, if (point.state == PointState.PLACE) 1.5f else 2.5f)
    }
    val radius = size / 2f
    canvas.drawCircle(radius, radius, radius - border.strokeWidth, fill)
    canvas.drawCircle(radius, radius, radius - border.strokeWidth, border)
    val icon = point.icon ?: if (point.state == PointState.DONE) PgmIcons.CHECK else null
    if (icon != null) {
        // ścieżka 24×24 przeskalowana do ~55% średnicy kółka
        val path = try {
            PathParser.createPathFromPathData(icon)
        } catch (e: RuntimeException) {
            null
        }
        if (path != null) {
            val iconSize = size * 0.55f
            val iconPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
                color = if (point.state == PointState.PLACE) Color.parseColor("#0B2A4A") else Color.WHITE
                style = Paint.Style.FILL
            }
            canvas.save()
            canvas.translate((size - iconSize) / 2f, (size - iconSize) / 2f)
            canvas.scale(iconSize / 24f, iconSize / 24f)
            canvas.drawPath(path, iconPaint)
            canvas.restore()
            return bitmap
        }
    }
    val text = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = Color.WHITE
        textSize = size * 0.45f
        textAlign = Paint.Align.CENTER
        isFakeBoldText = true
    }
    canvas.drawText(point.number.toString(), radius, radius - (text.descent() + text.ascent()) / 2, text)
    return bitmap
}

/** Niebieska kropka gracza (pomarańczowa w trybie demo). */
private fun playerBitmap(context: Context, simulated: Boolean): Bitmap {
    val size = dp(context, 22f).toInt()
    val bitmap = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888)
    val canvas = Canvas(bitmap)
    val radius = size / 2f
    canvas.drawCircle(radius, radius, radius, Paint(Paint.ANTI_ALIAS_FLAG).apply { color = Color.WHITE })
    canvas.drawCircle(radius, radius, radius * 0.7f, Paint(Paint.ANTI_ALIAS_FLAG).apply {
        color = if (simulated) Color.parseColor("#F08A24") else Color.parseColor("#1A73E8")
    })
    return bitmap
}
