package pl.plock.pgm.ui

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.graphics.BitmapFactory
import android.net.Uri
import android.provider.MediaStore
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContract
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.Image
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.Checkbox
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.core.content.FileProvider
import pl.plock.pgm.data.GameTask
import pl.plock.pgm.data.Submission
import pl.plock.pgm.data.Waypoint
import java.io.File

/** Pełnoekranowe okno zadania: treść, odpowiedź i – po wysłaniu – wynik. */
@Composable
fun TaskSheet(
    vm: AppViewModel,
    waypoint: Waypoint,
    task: GameTask,
    submission: Submission?,
    onClose: () -> Unit,
) {
    Dialog(onDismissRequest = onClose, properties = DialogProperties(usePlatformDefaultWidth = false)) {
        Surface(Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.surface) {
            Column(
                Modifier.fillMaxSize().statusBarsPadding().navigationBarsPadding()
                    .verticalScroll(rememberScrollState()).padding(20.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp),
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text(
                        "${waypoint.position}. ${waypoint.name}",
                        style = MaterialTheme.typography.labelLarge,
                        color = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.weight(1f),
                    )
                    TextButton(onClick = onClose) { Text("Zamknij ✕") }
                }
                Text(task.title, style = MaterialTheme.typography.headlineSmall, fontWeight = FontWeight.Bold)
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                    Tag(taskTypeLabel(task.taskType))
                    if (task.maxPoints > 0) Tag("⭐ ${task.maxPoints} pkt")
                }
                if (!task.description.isNullOrBlank()) {
                    Text(task.description, style = MaterialTheme.typography.bodyLarge)
                }

                var showHint by remember { mutableStateOf(false) }
                if (!task.hint.isNullOrBlank()) {
                    if (showHint) {
                        Surface(color = MaterialTheme.colorScheme.secondaryContainer, shape = RoundedCornerShape(12.dp)) {
                            Text("💡 ${task.hint}", modifier = Modifier.padding(12.dp))
                        }
                    } else {
                        TextButton(onClick = { showHint = true }) { Text("💡 Pokaż podpowiedź") }
                    }
                }

                if (submission != null) {
                    SubmissionResult(task, submission)
                } else {
                    AnswerForm(vm, task, onClose)
                }
            }
        }
    }
}

@Composable
private fun SubmissionResult(task: GameTask, submission: Submission) {
    val (title, color) = when (submission.isCorrect) {
        true -> "Dobra odpowiedź!" to MaterialTheme.colorScheme.primary
        false -> "Niestety, to nie ta odpowiedź" to MaterialTheme.colorScheme.error
        null -> (if (task.taskType == "info") "Przeczytane" else "Zadanie zaliczone") to MaterialTheme.colorScheme.primary
    }
    Surface(color = MaterialTheme.colorScheme.surfaceVariant, shape = RoundedCornerShape(16.dp)) {
        Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Text(title, color = color, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.titleMedium)
            Text("Zdobyte punkty: ${submission.pointsAwarded} / ${submission.maxPoints}")
            val correct = submission.correctOptionIds
            if (!correct.isNullOrEmpty() && submission.isCorrect == false) {
                val names = task.options.orEmpty().filter { it.id in correct }.joinToString(", ") { it.text }
                Text("Poprawna odpowiedź: $names", style = MaterialTheme.typography.bodySmall)
            }
            if (submission.reviewStatus == "unverified") {
                Text(
                    "Organizator gry może jeszcze sprawdzić Twoją odpowiedź.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}

@Composable
private fun AnswerForm(vm: AppViewModel, task: GameTask, onClose: () -> Unit) {
    val context = LocalContext.current
    val submit: (Answer) -> Unit = { answer -> vm.answer(task.id, answer) { ok -> if (ok) onClose() } }

    when (task.taskType) {
        "single_choice", "multiple_choice" -> {
            val multiple = task.taskType == "multiple_choice"
            var selected by remember { mutableStateOf(setOf<Int>()) }
            if (multiple) {
                Text("Zaznacz wszystkie poprawne odpowiedzi.", style = MaterialTheme.typography.bodySmall)
            }
            for (option in task.options.orEmpty()) {
                val checked = option.id in selected
                val toggle = {
                    selected = when {
                        !multiple -> setOf(option.id)
                        checked -> selected - option.id
                        else -> selected + option.id
                    }
                }
                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = if (checked) MaterialTheme.colorScheme.primaryContainer else MaterialTheme.colorScheme.surfaceVariant,
                    modifier = Modifier.fillMaxWidth().clickable { toggle() },
                ) {
                    Row(Modifier.padding(horizontal = 8.dp, vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
                        if (multiple) {
                            Checkbox(checked = checked, onCheckedChange = { toggle() })
                        } else {
                            RadioButton(selected = checked, onClick = { toggle() })
                        }
                        Text(option.text)
                    }
                }
            }
            SubmitButton(vm, "Sprawdź odpowiedź", enabled = selected.isNotEmpty()) {
                submit(Answer.Options(selected.toList()))
            }
        }

        "open_answer" -> {
            var text by remember { mutableStateOf("") }
            OutlinedTextField(
                value = text,
                onValueChange = { if (it.length <= 2000) text = it },
                label = { Text("Twoja odpowiedź") },
                modifier = Modifier.fillMaxWidth().heightIn(min = 120.dp),
            )
            SubmitButton(vm, "Wyślij odpowiedź", enabled = text.isNotBlank()) { submit(Answer.Text(text.trim())) }
        }

        "photo", "video" -> {
            val isVideo = task.taskType == "video"
            var file by remember { mutableStateOf<File?>(null) }
            var pendingFile by remember { mutableStateOf<File?>(null) }
            var comment by remember { mutableStateOf("") }

            val photoLauncher = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { ok ->
                if (ok && pendingFile?.let { it.length() > 0 } == true) file = pendingFile
            }
            val videoLauncher = rememberLauncherForActivityResult(CaptureVideoWithLimit(task.mediaMaxSeconds)) { ok ->
                if (ok && pendingFile?.let { it.length() > 0 } == true) file = pendingFile
            }

            if (!task.mediaRequirement.isNullOrBlank()) {
                Surface(color = MaterialTheme.colorScheme.surfaceVariant, shape = RoundedCornerShape(12.dp)) {
                    Text("Wymagania: ${task.mediaRequirement}", modifier = Modifier.padding(12.dp))
                }
            }
            if (isVideo && task.mediaMaxSeconds != null) {
                Text("Maksymalna długość nagrania: ${task.mediaMaxSeconds} s", style = MaterialTheme.typography.bodySmall)
            }

            val current = file
            if (current != null) {
                if (isVideo) {
                    Text("🎬 Nagranie gotowe (${current.length() / 1024} KB)", fontWeight = FontWeight.SemiBold)
                } else {
                    val bitmap = remember(current) { decodePreview(current) }
                    if (bitmap != null) {
                        Image(
                            bitmap = bitmap.asImageBitmap(),
                            contentDescription = "Zrobione zdjęcie",
                            contentScale = ContentScale.Crop,
                            modifier = Modifier.fillMaxWidth().heightIn(max = 280.dp).clip(RoundedCornerShape(16.dp)),
                        )
                    }
                }
            }

            OutlinedButton(
                onClick = {
                    val target = newMediaFile(context, if (isVideo) "mp4" else "jpg")
                    pendingFile = target
                    val uri = FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", target)
                    try {
                        if (isVideo) videoLauncher.launch(uri) else photoLauncher.launch(uri)
                    } catch (e: ActivityNotFoundException) {
                        vm.message = "Na tym urządzeniu nie ma aplikacji aparatu."
                    }
                },
                modifier = Modifier.fillMaxWidth(),
            ) {
                Text(
                    when {
                        current != null && isVideo -> "Nagraj ponownie"
                        current != null -> "Zrób zdjęcie ponownie"
                        isVideo -> "🎥 Nagraj film"
                        else -> "📷 Zrób zdjęcie"
                    },
                )
            }
            OutlinedTextField(
                value = comment,
                onValueChange = { if (it.length <= 500) comment = it },
                label = { Text("Komentarz (opcjonalnie)") },
                modifier = Modifier.fillMaxWidth(),
            )
            SubmitButton(vm, if (isVideo) "Wyślij nagranie" else "Wyślij zdjęcie", enabled = current != null) {
                current?.let { submit(Answer.Media(it, if (isVideo) "video/mp4" else "image/jpeg", comment)) }
            }
        }

        else -> SubmitButton(vm, "Przeczytałem – dalej", enabled = true) { submit(Answer.Info) }
    }
}

@Composable
private fun SubmitButton(vm: AppViewModel, label: String, enabled: Boolean, onClick: () -> Unit) {
    Button(onClick = onClick, enabled = enabled && !vm.busy, modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) {
        if (vm.busy) {
            CircularProgressIndicator(Modifier.size(20.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
        } else {
            Text(label, fontWeight = FontWeight.Bold)
        }
    }
}

/** Plik w pamięci podręcznej aplikacji (udostępniany aparatowi przez FileProvider). */
private fun newMediaFile(context: Context, extension: String): File {
    val dir = File(context.cacheDir, "media").apply { mkdirs() }
    return File(dir, "pgm_${System.currentTimeMillis()}.$extension")
}

/** Zmniejszony podgląd zdjęcia (pełne zdjęcie z aparatu zajęłoby za dużo pamięci). */
private fun decodePreview(file: File): android.graphics.Bitmap? {
    val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
    BitmapFactory.decodeFile(file.absolutePath, bounds)
    var sample = 1
    while (bounds.outWidth / sample > 1280 || bounds.outHeight / sample > 1280) sample *= 2
    return BitmapFactory.decodeFile(file.absolutePath, BitmapFactory.Options().apply { inSampleSize = sample })
}

/** Nagrywanie filmu aparatem z limitem długości i niższą jakością (mniejszy plik). */
private class CaptureVideoWithLimit(private val maxSeconds: Int?) : ActivityResultContract<Uri, Boolean>() {
    override fun createIntent(context: Context, input: Uri): Intent =
        Intent(MediaStore.ACTION_VIDEO_CAPTURE)
            .putExtra(MediaStore.EXTRA_OUTPUT, input)
            .putExtra(MediaStore.EXTRA_VIDEO_QUALITY, 0)
            .putExtra(MediaStore.EXTRA_DURATION_LIMIT, maxSeconds ?: 60)
            .addFlags(Intent.FLAG_GRANT_WRITE_URI_PERMISSION or Intent.FLAG_GRANT_READ_URI_PERMISSION)

    override fun parseResult(resultCode: Int, intent: Intent?): Boolean = resultCode == android.app.Activity.RESULT_OK
}
