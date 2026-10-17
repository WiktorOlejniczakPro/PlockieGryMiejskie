package pl.plock.pgm.ui

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import pl.plock.pgm.location.PlayerLocation
import kotlin.math.asin
import kotlin.math.cos
import kotlin.math.pow
import kotlin.math.roundToInt
import kotlin.math.sin
import kotlin.math.sqrt

fun difficultyLabel(value: String): String = when (value) {
    "easy" -> "Łatwa"
    "medium" -> "Średnia"
    "hard" -> "Trudna"
    else -> value
}

/** Polska odmiana: plural(3, "punkt", "punkty", "punktów") -> "punkty". */
fun plural(n: Int, one: String, few: String, many: String): String = when {
    n == 1 -> one
    n % 10 in 2..4 && n % 100 !in 12..14 -> few
    else -> many
}

fun durationLabel(minutes: Int?): String? = when {
    minutes == null || minutes <= 0 -> null
    minutes < 60 -> "$minutes min"
    minutes % 60 == 0 -> "${minutes / 60} h"
    else -> "${minutes / 60} h ${minutes % 60} min"
}

fun distanceLabel(meters: Double): String =
    if (meters < 1000) "${meters.roundToInt()} m" else String.format("%.1f km", meters / 1000).replace('.', ',')

fun taskTypeLabel(type: String): String = when (type) {
    "single_choice" -> "Pytanie jednokrotnego wyboru"
    "multiple_choice" -> "Pytanie wielokrotnego wyboru"
    "open_answer" -> "Odpowiedź otwarta"
    "photo" -> "Zrób zdjęcie"
    "video" -> "Nagraj film"
    "info" -> "Informacja"
    else -> type
}

/** "2026-10-17 14:17:52" -> "17.10.2026, 14:17" */
fun dateLabel(value: String?): String {
    if (value != null && value.length == 10) return "${value.substring(8, 10)}.${value.substring(5, 7)}.${value.substring(0, 4)}"
    if (value == null || value.length < 16) return "—"
    return "${value.substring(8, 10)}.${value.substring(5, 7)}.${value.substring(0, 4)}, ${value.substring(11, 16)}"
}

/** Odległość w metrach (wzór haversine) – ten sam co na serwerze. */
fun distanceMeters(lat1: Double, lng1: Double, lat2: Double, lng2: Double): Double {
    val r = 6371008.8
    val dLat = Math.toRadians(lat2 - lat1)
    val dLng = Math.toRadians(lng2 - lng1)
    val a = sin(dLat / 2).pow(2) + cos(Math.toRadians(lat1)) * cos(Math.toRadians(lat2)) * sin(dLng / 2).pow(2)
    return 2 * r * asin(sqrt(a).coerceAtMost(1.0))
}

fun PlayerLocation.distanceTo(lat: Double, lng: Double): Double = distanceMeters(latitude, longitude, lat, lng)

/**
 * Otwiera nawigację pieszą do punktu w Mapach Google (albo w przeglądarce, jeśli
 * aplikacji Map nie ma). Zwraca false, gdy nie ma czym otworzyć linku.
 */
fun openWalkingNavigation(context: Context, lat: Double, lng: Double): Boolean {
    val uri = Uri.parse("https://www.google.com/maps/dir/?api=1&destination=$lat,$lng&travelmode=walking")
    return try {
        context.startActivity(Intent(Intent.ACTION_VIEW, uri).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
        true
    } catch (e: ActivityNotFoundException) {
        false
    }
}

@Composable
fun LoadingBox(modifier: Modifier = Modifier) {
    Box(modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
        CircularProgressIndicator()
    }
}

@Composable
fun ErrorBox(message: String, onRetry: (() -> Unit)?, modifier: Modifier = Modifier) {
    Column(
        modifier.fillMaxSize().padding(32.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp, Alignment.CenterVertically),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Text("Coś poszło nie tak", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
        Text(message, textAlign = TextAlign.Center, color = MaterialTheme.colorScheme.onSurfaceVariant)
        if (onRetry != null) {
            Button(onClick = onRetry) { Text("Spróbuj ponownie") }
        }
    }
}

/** Mała etykieta, np. „Łatwa”, „60 min”. */
@Composable
fun Tag(text: String, background: Color = MaterialTheme.colorScheme.secondaryContainer, color: Color = MaterialTheme.colorScheme.onSecondaryContainer) {
    Text(
        text,
        style = MaterialTheme.typography.labelMedium,
        color = color,
        modifier = Modifier
            .background(background, RoundedCornerShape(50))
            .padding(horizontal = 10.dp, vertical = 4.dp),
    )
}
