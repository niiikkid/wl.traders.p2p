/**
 * Форматирует дату и время в удобочитаемый формат
 * 
 * @param {string} dateString - Строка с датой в формате ISO
 * @returns {string} - Отформатированная дата и время
 */
export function formatDateTime(dateString) {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    
    // Форматирование даты и времени
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear();
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    
    return `${day}.${month}.${year} ${hours}:${minutes}`;
} 