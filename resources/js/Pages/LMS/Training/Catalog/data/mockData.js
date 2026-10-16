export const mockTrainingCatalog = [];

// Helper to generate dynamic mock details based on the catalog entry
export function getMockCourseDetail(courseId) {
    // Find base details from catalog
    const baseCourse = mockTrainingCatalog.find((c) => c.id === Number(courseId)) || mockTrainingCatalog[0];

    return {
        id: baseCourse.id,
        title: baseCourse.title,
        provider: baseCourse.provider,
        image: baseCourse.image,
        description:
            baseCourse.description +
            ' Rincian ini disesuaikan untuk memberikan pemahaman menyeluruh tentang topik terkait dari perspektif praktis dan teoritis.',
        dateStart: baseCourse.date, // Simplifying dynamically
        dateEnd: '2024-12-31T23:59:00',
        progress: baseCourse.status === 'completed' ? 100 : baseCourse.progress,
        category: baseCourse.category,
        coordinators: [
            { id: 1, name: 'Andi Sutanto', initials: 'AS' },
            { id: 2, name: 'Budi Rahardjo', initials: 'BR' },
        ],
        participants: [
            { id: 1, name: 'Kevin', initials: 'K' },
            { id: 2, name: 'Lily', initials: 'L' },
            { id: 3, name: 'Mike', initials: 'M' },
            { id: 4, name: 'Siti', initials: 'S' },
        ],
        preTest: {
            name: `Pre-Test: ${baseCourse.title}`,
            duration: '30 Minutes',
            questions: '15 from 2 topics',
            status: baseCourse.status === 'completed' ? 'passed' : 'not-started',
        },
        checkIn: {
            status: baseCourse.status === 'completed' ? 'done' : 'pending',
        },
        materials: [
            {
                id: 1,
                name: `${baseCourse.title.substring(0, 15)}_Guidelines.pdf`,
                fileType: 'pdf',
                completed: baseCourse.status === 'completed' ? '05 May 2024' : null,
            },
            {
                id: 2,
                name: `Reference_Material.pdf`,
                fileType: 'pdf',
                completed: baseCourse.status === 'completed' ? '05 May 2024' : null,
            },
            {
                id: 3,
                name: `Checklist_${baseCourse.category}.xlsx`,
                fileType: 'xlsx',
                completed: baseCourse.status === 'completed' ? '05 May 2024' : null,
            },
        ],
        classes: [
            {
                id: 1,
                name: `Sesi ${baseCourse.category} - Pertemuan 1`,
                thumbnail: null,
                participants: [
                    { id: 1, initials: 'K' },
                    { id: 2, initials: 'S' },
                ],
                startDate: '10 Jun 2024 • 08:30',
                endDate: '12 Jun 2024 • 16:30',
                classType: baseCourse.category === 'Factory' ? 'Offline' : 'Hybrid',
            },
        ],
        videoQuizItems: [
            {
                id: 1,
                itemType: 'video',
                title: `Introduction to ${baseCourse.title}`,
                subtitle: `Part 1: Overview`,
                thumbnail: baseCourse.image,
                duration: '08:15',
                completed: baseCourse.status === 'completed',
            },
            {
                id: 2,
                itemType: 'quiz',
                title: `Quiz Part 1`,
                completed: baseCourse.status === 'completed',
            },
            {
                id: 3,
                itemType: 'video',
                title: `Advanced Concepts`,
                subtitle: `Part 2: Deep Dive`,
                thumbnail: baseCourse.image,
                duration: '12:30',
                completed: baseCourse.status === 'completed',
            },
            {
                id: 4,
                itemType: 'quiz',
                title: `Quiz Part 2`,
                completed: baseCourse.status === 'completed',
            },
        ],
        postTest: {
            name: `Post-Test: ${baseCourse.title}`,
            duration: '45 Minutes',
            questions: '25 from all topics',
            minScore: 80,
            status: baseCourse.status === 'completed' ? 'passed' : 'not-started',
        },
        evaluation: {
            name: 'Feedback & Evaluation',
            duration: '15 Minutes',
            questions: '10 Feedback Criteria',
            minScore: 0,
            status: baseCourse.status === 'completed' ? 'passed' : 'not-started',
        },
    };
}
